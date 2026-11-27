<?php
declare(strict_types=1);
namespace Correon;

/**
 * Promoção temporária do site: faixa com cronômetro e destaque com imagem ou vídeo.
 * Só aparece (e só entrega a mídia) entre o início e o fim, no horário de Brasília.
 */
final class Promo
{
    private const MAX_VIDEO = 31_457_280; // 30 MB
    private const VIDEO_TYPES = ['video/mp4' => '.mp4', 'video/webm' => '.webm'];
    private const EMPTY = ['title' => '', 'body' => '', 'button_label' => '', 'link_url' => '', 'annual_discount_pct' => '',
        'start_date' => '', 'start_time' => '00:00', 'end_date' => '', 'end_time' => '23:59', 'enabled' => '1'];

    /** Promoção em vigor agora (a que começou por último), ou null. @return array<string,mixed>|null */
    private static function current(): ?array
    {
        $now = date('Y-m-d H:i:s');
        return Db::one('SELECT p.*, m.mime FROM promotions p JOIN media m ON m.id = p.media_id
                        WHERE p.enabled = 1 AND p.starts_at <= ? AND p.ends_at > ? ORDER BY p.starts_at DESC, p.id DESC LIMIT 1', [$now, $now]);
    }

    /** Desconto da promoção em vigor para o plano anual, ou null. @return array{pct:float,title:string}|null */
    public static function annualDiscount(): ?array
    {
        $p = self::current();
        return $p && $p['annual_discount_pct'] !== null ? ['pct' => (float) $p['annual_discount_pct'], 'title' => $p['title']] : null;
    }

    /** Mesma conta (em centavos inteiros) feita no site, para os dois valores baterem. */
    public static function discounted(int $cents, float $pct): int
    {
        return (int) round($cents * (10000 - (int) round($pct * 100)) / 10000);
    }

    /** 10.00 -> "10"; 12.50 -> "12,5" */
    public static function pctLabel(float|string $pct): string
    {
        return rtrim(rtrim(number_format((float) $pct, 2, ',', ''), '0'), ',');
    }

    /** @return array{promo:array<string,mixed>|null} dados públicos para o site */
    public static function publicData(): array
    {
        $p = self::current();
        if (!$p) {
            return ['promo' => null];
        }
        $iso = static fn (string $d) => (new \DateTimeImmutable($d))->format(DATE_ATOM);
        return ['promo' => [
            'title' => $p['title'], 'text' => $p['body'], 'buttonLabel' => $p['button_label'], 'link' => $p['link_url'],
            'endsAt' => $iso($p['ends_at']), 'now' => $iso('now'),
            'annualDiscountPct' => $p['annual_discount_pct'] === null ? null : (float) $p['annual_discount_pct'],
            'type' => str_starts_with($p['mime'], 'video/') ? 'video' : 'image',
            'media' => '/api/v1/public/promocao/midia/' . (int) $p['id'] . '?v=' . (int) $p['media_id'],
        ]];
    }

    public static function publicMedia(int $id): never
    {
        $now = date('Y-m-d H:i:s');
        $p = Db::one('SELECT media_id FROM promotions WHERE id = ? AND enabled = 1 AND starts_at <= ? AND ends_at > ?', [$id, $now, $now]);
        if (!$p) {
            http_response_code(404);
            exit;
        }
        self::stream((int) $p['media_id']);
    }

    /** Entrega o arquivo aceitando "Range" (necessário para o vídeo tocar no iPhone e permitir avançar). */
    private static function stream(int $mediaId): never
    {
        $m = Db::one('SELECT filename, mime FROM media WHERE id = ?', [$mediaId]);
        $path = $m ? Media::dir() . '/' . basename($m['filename']) : '';
        if (!$m || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        $size = (int) filesize($path);
        $start = 0;
        $end = $size - 1;
        header('Content-Type: ' . $m['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=300');
        if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $r) && ($r[1] !== '' || $r[2] !== '')) {
            if ($r[1] === '') {
                $start = max(0, $size - (int) $r[2]);
            } else {
                $start = (int) $r[1];
                $end = $r[2] === '' ? $end : min($end, (int) $r[2]);
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */$size");
                exit;
            }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Length: ' . ($end - $start + 1));
        while (ob_get_level()) {
            ob_end_clean();
        }
        $f = fopen($path, 'rb');
        fseek($f, $start);
        for ($left = $end - $start + 1; $left > 0 && !feof($f) && !connection_aborted(); $left -= strlen($chunk)) {
            $chunk = (string) fread($f, (int) min(262144, $left));
            if ($chunk === '') {
                break;
            }
            echo $chunk;
            flush();
        }
        fclose($f);
        exit;
    }

    // ---- administração ----

    private static function admin(): array
    {
        return App::require('admin');
    }

    /** @param array<string,mixed> $p */
    public static function state(array $p): string
    {
        $now = date('Y-m-d H:i:s');
        if (!$p['enabled']) {
            return 'Desativada';
        }
        return $now < $p['starts_at'] ? 'Agendada' : ($now < $p['ends_at'] ? 'No ar' : 'Encerrada');
    }

    public static function index(): never
    {
        self::admin();
        $live = self::current();
        View::render('admin/promos', ['title' => 'Promoção', 'shell' => 'admin', 'active' => 'promocao', 'liveId' => $live ? (int) $live['id'] : 0,
            'rows' => Db::all('SELECT * FROM promotions ORDER BY starts_at DESC, id DESC LIMIT 100')]);
    }

    /** @return array<string,mixed> */
    private static function find(int $id): array
    {
        $p = Db::one('SELECT p.*, m.mime FROM promotions p JOIN media m ON m.id = p.media_id WHERE p.id = ?', [$id]);
        if (!$p) {
            App::notFound();
        }
        return $p;
    }

    /** @param array<string,string> $old */
    private static function form(?int $id, array $old, ?string $error, int $status): never
    {
        $current = $id ? self::find($id) : null;
        View::render('admin/promo_form', ['title' => $id ? 'Editar promoção' : 'Nova promoção', 'shell' => 'admin', 'active' => 'promocao',
            'id' => $id, 'old' => $old, 'error' => $error, 'mime' => $current['mime'] ?? null], $status);
    }

    public static function newForm(): never
    {
        self::admin();
        self::form(null, ['start_date' => date('Y-m-d')] + self::EMPTY, null, 200);
    }

    public static function edit(int $id): never
    {
        self::admin();
        $p = self::find($id);
        self::form($id, [
            'title' => $p['title'], 'body' => (string) $p['body'], 'button_label' => (string) $p['button_label'], 'link_url' => (string) $p['link_url'],
            'annual_discount_pct' => $p['annual_discount_pct'] === null ? '' : self::pctLabel($p['annual_discount_pct']),
            'start_date' => substr($p['starts_at'], 0, 10), 'start_time' => substr($p['starts_at'], 11, 5),
            'end_date' => substr($p['ends_at'], 0, 10), 'end_time' => substr($p['ends_at'], 11, 5), 'enabled' => (string) (int) $p['enabled'],
        ], null, 200);
    }

    public static function create(): never
    {
        self::save(null);
    }

    public static function update(int $id): never
    {
        self::save($id);
    }

    public static function media(int $id): never
    {
        self::admin();
        self::stream((int) self::find($id)['media_id']);
    }

    public static function delete(int $id): never
    {
        $me = self::admin();
        $p = self::find($id);
        Db::run('DELETE FROM promotions WHERE id = ?', [$id]);
        Media::delete((int) $p['media_id']);
        Audit::log('promo_deleted', (int) $me['id'], 'promo', $id, ['title' => $p['title']]);
        Http::flash('ok', 'Promoção excluída.');
        Http::redirect('/admin/promocao');
    }

    private static function moment(string $date, string $time): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$date $time");
        return $d && $d->format('Y-m-d H:i') === "$date $time" ? $d->format('Y-m-d H:i:00') : null;
    }

    private static function validLink(string $url): bool
    {
        if (str_starts_with($url, '#') || (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\'))) {
            return true;
        }
        return str_starts_with(strtolower($url), 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /** Grava a imagem (regravada) ou o vídeo (conferido pelo conteúdo). @param array<string,mixed> $file @return array{id:int|null,error:string|null} */
    private static function storeFile(array $file, int $userId): array
    {
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            return ['id' => null, 'error' => 'Falha no envio do arquivo (vídeo: até 30 MB; imagem: até 4 MB).'];
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!str_starts_with($mime, 'video/')) {
            return Media::store($file, $userId, 1920); // ocupa a tela inteira
        }
        if (!isset(self::VIDEO_TYPES[$mime])) {
            return ['id' => null, 'error' => 'Vídeo aceito: MP4 ou WebM.'];
        }
        if (filesize($tmp) > self::MAX_VIDEO) {
            return ['id' => null, 'error' => 'O vídeo passa de 30 MB.'];
        }
        $name = bin2hex(random_bytes(16)) . self::VIDEO_TYPES[$mime];
        if (!move_uploaded_file($tmp, Media::dir() . '/' . $name)) {
            return ['id' => null, 'error' => 'Não foi possível salvar o vídeo.'];
        }
        Db::run('INSERT INTO media (filename, mime, width, height, uploaded_by) VALUES (?,?,0,0,?)', [$name, $mime, $userId]);
        return ['id' => (int) Db::pdo()->lastInsertId(), 'error' => null];
    }

    private static function save(?int $id): never
    {
        $me = self::admin();
        $current = $id ? self::find($id) : null;
        $v = [];
        foreach (array_keys(self::EMPTY) as $k) {
            $v[$k] = Http::input($k);
        }
        $v['enabled'] = $v['enabled'] === '1' ? '1' : '0';
        $fail = static fn (string $msg) => self::form($id, $v, $msg, 422);

        if (!Http::formComplete()) {
            $fail('O envio veio incompleto (arquivo grande demais?). Nada foi salvo.');
        }
        if (mb_strlen($v['title']) < 2 || mb_strlen($v['title']) > 120) {
            $fail('Informe o título (2 a 120 caracteres).');
        }
        if (mb_strlen($v['body']) > 300 || mb_strlen($v['button_label']) > 40) {
            $fail('Texto: até 300 caracteres. Botão: até 40.');
        }
        if ($v['link_url'] !== '' && (mb_strlen($v['link_url']) > 500 || !self::validLink($v['link_url']))) {
            $fail('Link inválido. Use https://..., um caminho como /cadastro ou uma seção como #planos.');
        }
        if (($v['button_label'] === '') !== ($v['link_url'] === '')) {
            $fail('Para ter botão, preencha o texto do botão e o link (ou deixe os dois vazios).');
        }
        $pct = null;
        if ($v['annual_discount_pct'] !== '') {
            $pct = preg_match('/^\d{1,2}([.,]\d{1,2})?$/', $v['annual_discount_pct']) ? (float) str_replace(',', '.', $v['annual_discount_pct']) : 0.0;
            if ($pct <= 0) {
                $fail('Desconto no anual: informe um percentual maior que 0 e menor que 100 (ex.: 10 ou 12,5), ou deixe vazio.');
            }
        }
        $start = self::moment($v['start_date'], $v['start_time']);
        $end = self::moment($v['end_date'], $v['end_time']);
        if ($start === null || $end === null) {
            $fail('Informe a data e a hora de início e de fim.');
        }
        if ($end <= $start) {
            $fail('O fim precisa ser depois do início.');
        }
        $mediaId = $current ? (int) $current['media_id'] : null;
        $hasFile = ($_FILES['media']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$hasFile && $mediaId === null) {
            $fail('Envie a imagem ou o vídeo da promoção.');
        }
        $old = null;
        if ($hasFile) {
            $r = self::storeFile($_FILES['media'], (int) $me['id']);
            if ($r['error'] !== null) {
                $fail($r['error']);
            }
            $old = $mediaId;
            $mediaId = (int) $r['id'];
        }
        $n = static fn (string $s) => $s === '' ? null : $s;
        $row = [$v['title'], $n($v['body']), $n($v['button_label']), $n($v['link_url']), $mediaId, $start, $end, (int) $v['enabled'], $pct];
        if ($id) {
            Db::run('UPDATE promotions SET title = ?, body = ?, button_label = ?, link_url = ?, media_id = ?, starts_at = ?, ends_at = ?, enabled = ?, annual_discount_pct = ? WHERE id = ?', [...$row, $id]);
        } else {
            Db::run('INSERT INTO promotions (title, body, button_label, link_url, media_id, starts_at, ends_at, enabled, annual_discount_pct, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)', [...$row, $me['id']]);
            $id = (int) Db::pdo()->lastInsertId();
        }
        if ($old !== null) {
            Media::delete($old);
        }
        Audit::log('promo_saved', (int) $me['id'], 'promo', $id, ['starts' => $start, 'ends' => $end, 'enabled' => $v['enabled'], 'annual_discount_pct' => $pct]);
        Http::flash('ok', 'Promoção salva.');
        Http::redirect('/admin/promocao/' . $id);
    }
}
