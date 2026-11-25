<?php
declare(strict_types=1);
namespace Correon;

/**
 * Conteúdo público (Hero 7). Publicação respeita consentimento:
 * - atleta cadastrado: precisa da autorização ATIVA dele (se revogar, o conteúdo sai do ar na hora);
 * - resultado de pessoa sem cadastro, ou qualquer foto: o admin declara ter autorização.
 */
final class Content
{
    public const TYPES = ['result' => 'Resultado', 'news' => 'Notícia', 'event' => 'Evento'];
    public const STATUS = ['draft' => 'Rascunho', 'published' => 'Publicado', 'archived' => 'Arquivado'];
    public const EVENT_STATUS = ['' => 'Automático pela data', 'proximo' => 'Próximo', 'andamento' => 'Em andamento', 'realizado' => 'Realizado'];
    public const CONSENT_TYPE = 'publicacao';
    public const CONSENT_VERSION = 'publicacao-rascunho-0';

    /** Condição SQL de "visível ao público" para o alias c. */
    private const VISIBLE = "c.status = 'published' AND (c.publish_at IS NULL OR c.publish_at <= NOW())
        AND (c.athlete_id IS NULL OR EXISTS (SELECT 1 FROM consents k WHERE k.user_id = c.athlete_id AND k.type = 'publicacao' AND k.revoked_at IS NULL))";

    public static function hasConsent(int $userId): bool
    {
        return Db::one("SELECT id FROM consents WHERE user_id = ? AND type = ? AND revoked_at IS NULL LIMIT 1", [$userId, self::CONSENT_TYPE]) !== null;
    }

    public static function setConsent(int $userId, bool $granted): void
    {
        if ($granted && !self::hasConsent($userId)) {
            Db::run('INSERT INTO consents (user_id, type, version, ip) VALUES (?,?,?,?)', [$userId, self::CONSENT_TYPE, self::CONSENT_VERSION, Http::ip()]);
            Audit::log('consent_granted', $userId, 'user', $userId, ['type' => self::CONSENT_TYPE]);
        } elseif (!$granted && self::hasConsent($userId)) {
            Db::run('UPDATE consents SET revoked_at = NOW() WHERE user_id = ? AND type = ? AND revoked_at IS NULL', [$userId, self::CONSENT_TYPE]);
            Audit::log('consent_revoked', $userId, 'user', $userId, ['type' => self::CONSENT_TYPE]);
        }
    }

    private static function validUrl(string $u): bool
    {
        return $u === '' || (strlen($u) <= 300 && filter_var($u, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $u) === 1);
    }

    /**
     * @param array<string,string> $in
     * @return array{0:array<string,mixed>,1:list<string>} [colunas, erros]
     */
    public static function validate(string $type, array $in, bool $publishing, bool $hasImage): array
    {
        $e = [];
        $clean = static fn (string $k) => trim(preg_replace('/[ \t]+/u', ' ', $in[$k] ?? '') ?? '');
        $nul = static fn (string $v) => $v === '' ? null : $v;
        $title = $clean('title');
        $date = $clean('content_date');
        $athleteId = ctype_digit($clean('athlete_id')) ? (int) $clean('athlete_id') : null;
        if ($athleteId !== null && !Db::one("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.slug = 'athlete' WHERE u.id = ?", [$athleteId])) {
            $e[] = 'Atleta inválido.';
            $athleteId = null;
        }
        if (mb_strlen($title) < 2 || mb_strlen($title) > 160) {
            $e[] = ($type === 'result' ? 'Informe o nome do atleta' : ($type === 'news' ? 'Informe o título' : 'Informe o nome do evento')) . ' (2 a 160 caracteres).';
        }
        if (!Billing::validDate($date)) {
            $e[] = 'Informe a data.';
        }
        foreach (['category' => 60, 'competition' => 160, 'distance' => 60, 'result_text' => 60, 'placement' => 60, 'summary' => 600, 'place' => 160, 'event_time' => 20, 'image_alt' => 200] as $k => $max) {
            if (mb_strlen($clean($k)) > $max) {
                $e[] = "Texto muito longo no campo $k (máx. $max).";
            }
        }
        foreach (['link_url', 'registration_url'] as $k) {
            if (!self::validUrl($clean($k))) {
                $e[] = 'Os links precisam começar com http:// ou https://.';
            }
        }
        $required = ['result' => ['category' => 'a modalidade', 'competition' => 'a competição', 'result_text' => 'o resultado ou tempo'],
            'news' => ['category' => 'a categoria', 'summary' => 'o resumo'], 'event' => ['category' => 'a modalidade', 'place' => 'o local']][$type];
        foreach ($required as $k => $label) {
            if ($clean($k) === '') {
                $e[] = "Informe $label.";
            }
        }
        $eventStatus = $clean('event_status');
        if (!isset(self::EVENT_STATUS[$eventStatus])) {
            $e[] = 'Situação do evento inválida.';
            $eventStatus = '';
        }
        $publishAt = null;
        if ($clean('publish_at') !== '') {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $clean('publish_at'));
            if (!$d) {
                $e[] = 'Data de agendamento inválida.';
            } else {
                $publishAt = $d->format('Y-m-d H:i:00');
            }
        }
        $declared = ($in['consent_declared'] ?? '') === '1';
        if ($publishing) {
            if ($athleteId !== null && !self::hasConsent($athleteId)) {
                $e[] = 'Este atleta não autorizou publicações. Salve como rascunho e peça que ele autorize em "Privacidade".';
            }
            if ($type === 'result' && $athleteId === null && !$declared) {
                $e[] = 'Marque que você tem autorização da pessoa para publicar o resultado.';
            }
            if ($hasImage && !$declared) {
                $e[] = 'Marque que você tem autorização de uso da imagem.';
            }
        }
        return [[
            'title' => $title, 'content_date' => $date, 'category' => $nul($clean('category')), 'competition' => $nul($clean('competition')),
            'distance' => $nul($clean('distance')), 'result_text' => $nul($clean('result_text')), 'placement' => $nul($clean('placement')),
            'summary' => $nul(trim($in['summary'] ?? '')), 'place' => $nul($clean('place')), 'event_time' => $nul($clean('event_time')),
            'event_status' => $nul($eventStatus), 'link_url' => $nul($clean('link_url')), 'registration_url' => $nul($clean('registration_url')),
            'image_alt' => $nul($clean('image_alt')), 'athlete_id' => $athleteId, 'consent_declared' => $declared ? 1 : 0, 'publish_at' => $publishAt,
        ], $e];
    }

    /** Dados públicos para a Hero 7: somente campos exibidos, nunca ids de usuário. @return array<string,list<array<string,mixed>>> */
    public static function publicData(): array
    {
        $out = ['results' => [], 'news' => [], 'events' => []];
        foreach (['result' => 'results', 'news' => 'news', 'event' => 'events'] as $type => $key) {
            $rows = Db::all('SELECT c.* FROM contents c WHERE c.type = ? AND ' . self::VISIBLE . ' ORDER BY c.content_date DESC, c.id DESC LIMIT 24', [$type]);
            foreach ($rows as $r) {
                $item = ['date' => $r['content_date'], 'image' => $r['image_id'] ? '/api/v1/public/imagem/' . (int) $r['image_id'] : null,
                    'imageAlt' => $r['image_alt'] ?: $r['title'], 'href' => $r['link_url']];
                if ($type === 'result') {
                    $item += ['athlete' => $r['title'], 'modality' => $r['category'], 'competition' => $r['competition'], 'distance' => $r['distance'],
                        'result' => $r['result_text'], 'placement' => $r['placement'], 'text' => $r['summary']];
                } elseif ($type === 'news') {
                    $item += ['title' => $r['title'], 'category' => $r['category'], 'summary' => $r['summary']];
                } else {
                    $item += ['name' => $r['title'], 'modality' => $r['category'], 'time' => $r['event_time'], 'place' => $r['place'], 'description' => $r['summary'],
                        'registrationHref' => $r['registration_url'], 'status' => $r['event_status'] ?: ($r['content_date'] < date('Y-m-d') ? 'realizado' : 'proximo')];
                }
                $out[$key][] = $item;
            }
        }
        return $out;
    }

    public static function imageIsPublic(int $mediaId): bool
    {
        return Db::one('SELECT c.id FROM contents c WHERE c.image_id = ? AND ' . self::VISIBLE . ' LIMIT 1', [$mediaId]) !== null;
    }
}
