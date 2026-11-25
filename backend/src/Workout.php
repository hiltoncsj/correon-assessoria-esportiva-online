<?php
declare(strict_types=1);
namespace Correon;

/** Regras de domínio de treinos planejados: validação, itens, versões e vínculo treinador-atleta. */
final class Workout
{
    public const DISCIPLINES = ['corrida' => 'Corrida', 'ciclismo' => 'Ciclismo', 'natacao' => 'Natação', 'musculacao' => 'Musculação'];
    public const MODALITY_DISCIPLINES = [
        'corrida' => ['corrida'],
        'triathlon' => ['natacao', 'ciclismo', 'corrida'],
        'musculacao' => ['musculacao'],
    ];
    public const STATUS = ['draft' => 'Rascunho', 'published' => 'Publicado', 'cancelled' => 'Cancelado'];
    public const OUTCOMES = ['completed' => 'Realizado', 'partial' => 'Realizado em parte', 'skipped' => 'Não realizado'];
    public const MAX_ITEMS = 40;

    public static function isStrength(string $discipline): bool
    {
        return $discipline === 'musculacao';
    }

    // ---- vínculo (autorização) ----

    /** Slugs das modalidades em que o treinador atende o atleta (vínculo ativo). @return list<string> */
    public static function linkedModalities(int $trainerId, int $athleteId): array
    {
        return array_column(Db::all(
            "SELECT m.slug FROM trainer_athletes ta JOIN modalities m ON m.id = ta.modality_id
             WHERE ta.trainer_id = ? AND ta.athlete_id = ? AND ta.status = 'active' ORDER BY m.id",
            [$trainerId, $athleteId]
        ), 'slug');
    }

    public static function modalityId(string $slug): ?int
    {
        $r = Db::one('SELECT id FROM modalities WHERE slug = ?', [$slug]);
        return $r ? (int) $r['id'] : null;
    }

    // ---- leitura ----

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT w.*, m.slug AS modality, m.name AS modality_name, a.name AS athlete_name, t.name AS template_name
             FROM workouts w JOIN modalities m ON m.id = w.modality_id JOIN users a ON a.id = w.athlete_id
             LEFT JOIN workout_templates t ON t.id = w.source_template_id WHERE w.id = ?',
            [$id]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function items(int $workoutId): array
    {
        return Db::all('SELECT label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, notes
                        FROM workout_items WHERE workout_id = ? ORDER BY position', [$workoutId]);
    }

    /** @return array<string,mixed>|null */
    public static function log(int $workoutId): ?array
    {
        return Db::one('SELECT * FROM workout_logs WHERE workout_id = ?', [$workoutId]);
    }

    // ---- validação ----

    /**
     * @param array<string,string> $in
     * @return array{0:array<string,string>,1:array<string,string>} [dados limpos, erros]
     */
    public static function validateHeader(array $in, string $modality, ?string $fixedDiscipline = null): array
    {
        $e = [];
        $title = trim(preg_replace('/\s+/u', ' ', $in['title'] ?? '') ?? '');
        $date = $in['scheduled_date'] ?? '';
        $notes = trim($in['notes'] ?? '');
        $discipline = $fixedDiscipline ?? ($in['discipline'] ?? '');

        if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            $e['title'] = 'Informe o título (2 a 120 caracteres).';
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $e['scheduled_date'] = 'Data inválida.';
        } elseif ($d < new \DateTimeImmutable('-1 year') || $d > new \DateTimeImmutable('+2 years')) {
            $e['scheduled_date'] = 'A data deve estar entre 1 ano atrás e 2 anos à frente.';
        }
        if (!in_array($discipline, self::MODALITY_DISCIPLINES[$modality] ?? [], true)) {
            $e['discipline'] = 'Disciplina inválida para esta modalidade.';
        }
        if (mb_strlen($notes) > 1000) {
            $e['notes'] = 'Máximo de 1000 caracteres.';
        }
        return [['title' => $title, 'scheduled_date' => $date, 'notes' => $notes, 'discipline' => $discipline], $e];
    }

    /**
     * Converte as linhas do formulário. Linhas totalmente vazias são ignoradas.
     * @param mixed $raw $_POST['items']
     * @return array{0:list<array<string,mixed>>,1:list<string>} [itens, erros]
     */
    public static function parseItems(mixed $raw, string $discipline): array
    {
        $items = [];
        $errors = [];
        if (!is_array($raw)) {
            return [[], []];
        }
        $strength = self::isStrength($discipline);
        $n = 0;
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $r = [];
            foreach (['label', 'sets', 'reps', 'load_text', 'rest_seconds', 'duration_minutes', 'distance_km', 'target', 'notes'] as $k) {
                $r[$k] = is_string($row[$k] ?? null) ? trim($row[$k]) : '';
            }
            if (implode('', $r) === '') {
                continue;
            }
            $n++;
            $tag = "Linha $n: ";
            if ($n > self::MAX_ITEMS) {
                $errors[] = 'No máximo ' . self::MAX_ITEMS . ' linhas por treino.';
                break;
            }
            if ($r['label'] === '' || mb_strlen($r['label']) > 120) {
                $errors[] = $tag . ($strength ? 'informe o exercício (até 120 caracteres).' : 'informe a etapa (até 120 caracteres).');
            }
            $it = ['label' => $r['label'], 'notes' => $r['notes'] === '' ? null : $r['notes'],
                'sets' => null, 'reps' => null, 'load_text' => null, 'rest_seconds' => null,
                'duration_minutes' => null, 'distance_km' => null, 'target' => null];
            if (mb_strlen($r['notes']) > 300) {
                $errors[] = $tag . 'observação com mais de 300 caracteres.';
            }
            if ($strength) {
                $it['sets'] = self::intField($r['sets'], 1, 99, $tag . 'séries entre 1 e 99.', $errors);
                $it['rest_seconds'] = self::intField($r['rest_seconds'], 0, 3600, $tag . 'intervalo entre 0 e 3600 segundos.', $errors);
                foreach (['reps' => 20, 'load_text' => 30] as $k => $max) {
                    if (mb_strlen($r[$k]) > $max) {
                        $errors[] = $tag . "$k com mais de $max caracteres.";
                    }
                    $it[$k] = $r[$k] === '' ? null : $r[$k];
                }
            } else {
                $it['duration_minutes'] = self::intField($r['duration_minutes'], 1, 1440, $tag . 'duração entre 1 e 1440 minutos.', $errors);
                if ($r['distance_km'] !== '') {
                    $km = str_replace(',', '.', $r['distance_km']);
                    if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $km) || (float) $km <= 0) {
                        $errors[] = $tag . 'distância inválida (km, ex.: 5 ou 5,5).';
                    } else {
                        $it['distance_km'] = $km;
                    }
                }
                if (mb_strlen($r['target']) > 120) {
                    $errors[] = $tag . 'alvo com mais de 120 caracteres.';
                }
                $it['target'] = $r['target'] === '' ? null : $r['target'];
            }
            $items[] = $it;
        }
        return [$items, $errors];
    }

    /** @param list<string> $errors */
    private static function intField(string $v, int $min, int $max, string $msg, array &$errors): ?int
    {
        if ($v === '') {
            return null;
        }
        if (!ctype_digit($v) || (int) $v < $min || (int) $v > $max) {
            $errors[] = $msg;
            return null;
        }
        return (int) $v;
    }

    // ---- escrita (chamar dentro de transação) ----

    /** @param list<array<string,mixed>> $items */
    public static function replaceItems(int $workoutId, array $items): void
    {
        Db::run('DELETE FROM workout_items WHERE workout_id = ?', [$workoutId]);
        foreach ($items as $i => $it) {
            Db::run(
                'INSERT INTO workout_items (workout_id, position, label, sets, reps, load_text, rest_seconds, duration_minutes, distance_km, target, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$workoutId, $i + 1, $it['label'], $it['sets'], $it['reps'], $it['load_text'], $it['rest_seconds'],
                    $it['duration_minutes'], $it['distance_km'], $it['target'], $it['notes']]
            );
        }
    }

    /** Grava a foto completa do estado atual do treino como nova linha do histórico. */
    public static function snapshot(int $workoutId, string $note, int $userId): void
    {
        $w = self::find($workoutId);
        if (!$w) {
            return;
        }
        $snap = [
            'title' => $w['title'], 'discipline' => $w['discipline'], 'scheduled_date' => $w['scheduled_date'],
            'notes' => $w['notes'], 'status' => $w['status'], 'items' => self::items($workoutId),
        ];
        Db::run('INSERT INTO workout_versions (workout_id, version, change_note, snapshot, changed_by) VALUES (?,?,?,?,?)',
            [$workoutId, $w['version'], $note, json_encode($snap, JSON_UNESCAPED_UNICODE), $userId]);
    }

    /** @return list<array<string,mixed>> */
    public static function versions(int $workoutId): array
    {
        return Db::all('SELECT v.version, v.change_note, v.created_at, u.name AS author
                        FROM workout_versions v JOIN users u ON u.id = v.changed_by WHERE v.workout_id = ? ORDER BY v.version DESC', [$workoutId]);
    }

    public static function noteLabel(string $note): string
    {
        return ['created' => 'Criado', 'updated' => 'Alterado', 'published' => 'Publicado', 'unpublished' => 'Voltou a rascunho', 'cancelled' => 'Cancelado'][$note] ?? $note;
    }
}
