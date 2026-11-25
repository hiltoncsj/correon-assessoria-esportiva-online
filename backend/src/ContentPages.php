<?php
declare(strict_types=1);
namespace Correon;

/** Telas de conteúdo e comunicados (admin) e de privacidade/avisos (atleta). */
final class ContentPages
{
    private const FIELDS = ['title', 'content_date', 'category', 'competition', 'distance', 'result_text', 'placement', 'summary', 'place',
        'event_time', 'event_status', 'link_url', 'registration_url', 'image_alt', 'athlete_id', 'consent_declared', 'publish_at'];

    // ---- admin: conteúdo ----

    public static function index(): never
    {
        App::require('admin');
        $type = (string) (is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '');
        $type = isset(Content::TYPES[$type]) ? $type : '';
        View::render('admin/content_list', ['title' => 'Conteúdo', 'shell' => 'admin', 'active' => 'conteudo', 'type' => $type,
            'rows' => Db::all('SELECT id, type, title, content_date, status, publish_at, athlete_id FROM contents ' . ($type ? 'WHERE type = ?' : '') . ' ORDER BY content_date DESC, id DESC LIMIT 200', $type ? [$type] : [])]);
    }

    /** @param array<string,mixed>|null $c @param array<string,string> $vals @param list<string> $errors */
    private static function form(string $type, ?array $c, array $vals, array $errors, int $status): never
    {
        View::render('admin/content_form', ['title' => $c ? 'Editar' : 'Novo', 'shell' => 'admin', 'active' => 'conteudo', 'type' => $type, 'c' => $c, 'vals' => $vals, 'errors' => $errors,
            'athletes' => Db::all("SELECT u.id, u.name, EXISTS (SELECT 1 FROM consents k WHERE k.user_id = u.id AND k.type = 'publicacao' AND k.revoked_at IS NULL) AS ok
                                   FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.slug = 'athlete' WHERE u.status = 'active' ORDER BY u.name LIMIT 1000")], $status);
    }

    public static function newForm(): never
    {
        App::require('admin');
        $type = (string) ($_GET['tipo'] ?? '');
        if (!isset(Content::TYPES[$type])) {
            App::notFound();
        }
        $vals = array_fill_keys(self::FIELDS, '');
        $vals['content_date'] = date('Y-m-d');
        self::form($type, null, $vals, [], 200);
    }

    /** @return array<string,mixed> */
    private static function find(int $id): array
    {
        $c = Db::one('SELECT * FROM contents WHERE id = ?', [$id]);
        if (!$c) {
            App::notFound();
        }
        return $c;
    }

    public static function edit(int $id): never
    {
        App::require('admin');
        $c = self::find($id);
        $vals = [];
        foreach (self::FIELDS as $k) {
            $vals[$k] = (string) ($c[$k] ?? '');
        }
        $vals['publish_at'] = $c['publish_at'] ? date('Y-m-d\TH:i', strtotime($c['publish_at'])) : '';
        self::form($c['type'], $c, $vals, [], 200);
    }

    public static function create(): never
    {
        $me = App::require('admin');
        $type = Http::input('type');
        if (!isset(Content::TYPES[$type])) {
            App::notFound();
        }
        self::save((int) $me['id'], $type, null);
    }

    public static function update(int $id): never
    {
        $me = App::require('admin');
        $c = self::find($id);
        $action = Http::input('action');
        if ($action === 'archive' || $action === 'unpublish') {
            Db::run('UPDATE contents SET status = ?, updated_by = ? WHERE id = ?', [$action === 'archive' ? 'archived' : 'draft', $me['id'], $id]);
            Audit::log('content_' . ($action === 'archive' ? 'archived' : 'unpublished'), (int) $me['id'], 'content', $id);
            Http::flash('ok', $action === 'archive' ? 'Arquivado (continua no histórico, fora do site).' : 'Voltou a rascunho (fora do site).');
            Http::redirect('/admin/conteudo/' . $id);
        }
        self::save((int) $me['id'], $c['type'], $c);
    }

    /** @param array<string,mixed>|null $existing */
    private static function save(int $adminId, string $type, ?array $existing): never
    {
        if (!Http::formComplete()) {
            Http::flash('err', 'O envio chegou incompleto (foto grande demais?). Nada foi salvo.');
            Http::redirect($existing ? '/admin/conteudo/' . (int) $existing['id'] : '/admin/conteudo/novo?tipo=' . $type);
        }
        $in = [];
        foreach (self::FIELDS as $k) {
            $in[$k] = Http::input($k);
        }
        $publish = Http::input('action') === 'publish';
        $removeImage = Http::input('remove_image') === '1';
        $upload = $_FILES['image'] ?? null;
        $hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $imageId = $existing && !$removeImage ? ($existing['image_id'] === null ? null : (int) $existing['image_id']) : null;
        $keepPublished = $existing && $existing['status'] === 'published';
        [$data, $errors] = Content::validate($type, $in, $publish || $keepPublished, $hasUpload || $imageId !== null);
        if (!$errors && $hasUpload) {
            $r = Media::store($upload, $adminId);
            if ($r['error'] !== null) {
                $errors[] = $r['error'];
            } else {
                $imageId = $r['id'];
            }
        }
        if ($errors) {
            self::form($type, $existing, $in, $errors, 422);
        }
        $status = $publish ? 'published' : ($existing['status'] ?? 'draft');
        $data += ['image_id' => $imageId, 'status' => $status, 'updated_by' => $adminId];
        if ($existing) {
            $set = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($data)));
            Db::run("UPDATE contents SET $set WHERE id = ?", [...array_values($data), $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $data += ['type' => $type, 'created_by' => $adminId];
            Db::run('INSERT INTO contents (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data));
            $id = (int) Db::pdo()->lastInsertId();
        }
        Audit::log($publish ? 'content_published' : 'content_saved', $adminId, 'content', $id, ['type' => $type]);
        Http::flash('ok', $publish ? ($data['publish_at'] && $data['publish_at'] > date('Y-m-d H:i:s') ? 'Agendado: entra no site na data escolhida.' : 'Publicado no site.') : 'Salvo.');
        Http::redirect('/admin/conteudo/' . $id);
    }

    public static function adminImage(int $id): never
    {
        App::require('admin');
        Media::output($id, false);
    }

    // ---- admin: comunicados ----

    public static function announcements(): never
    {
        App::require('admin');
        View::render('admin/announcements', ['title' => 'Comunicados', 'shell' => 'admin', 'active' => 'comunicados',
            'sent' => Db::all("SELECT l.created_at, l.details, u.name AS who FROM audit_log l LEFT JOIN users u ON u.id = l.user_id WHERE l.action = 'announcement_sent' ORDER BY l.id DESC LIMIT 30")]);
    }

    public static function announce(): never
    {
        $me = App::require('admin');
        $title = Http::input('title');
        $body = Http::input('body');
        $audience = Http::input('audience');
        if (mb_strlen($title) < 3 || mb_strlen($title) > 120 || mb_strlen($body) > 600 || !in_array($audience, ['all', 'corrida', 'triathlon', 'musculacao'], true)) {
            Http::flash('err', 'Informe o título (3 a 120), a mensagem (até 600) e o público.');
            Http::redirect('/admin/comunicados');
        }
        $sql = "SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.slug = 'athlete' WHERE u.status = 'active'";
        $params = [];
        if ($audience !== 'all') {
            $sql .= " AND EXISTS (SELECT 1 FROM trainer_athletes ta JOIN modalities m ON m.id = ta.modality_id WHERE ta.athlete_id = u.id AND ta.status = 'active' AND m.slug = ?)";
            $params[] = $audience;
        }
        $ids = array_column(Db::all($sql, $params), 'id');
        foreach ($ids as $id) {
            Notify::send((int) $id, $title, $body === '' ? null : $body, '/app/avisos');
        }
        Audit::log('announcement_sent', (int) $me['id'], null, null, ['title' => $title, 'audience' => $audience, 'recipients' => count($ids)]);
        Http::flash('ok', count($ids) . ' atleta(s) avisado(s).');
        Http::redirect('/admin/comunicados');
    }

    // ---- atleta ----

    public static function notices(): never
    {
        $u = App::require('athlete');
        $rows = Db::all('SELECT id, title, body, link, created_at, read_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 60', [$u['id']]);
        Db::run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$u['id']]);
        View::render('athlete/notices', ['title' => 'Avisos', 'shell' => 'athlete', 'active' => 'avisos', 'rows' => $rows]);
    }

    public static function privacy(): never
    {
        $u = App::require('athlete');
        View::render('athlete/privacy', ['title' => 'Privacidade', 'shell' => 'athlete', 'active' => 'privacidade', 'granted' => Content::hasConsent((int) $u['id'])]);
    }

    public static function savePrivacy(): never
    {
        $u = App::require('athlete');
        $grant = Http::input('publicacao') === '1';
        Content::setConsent((int) $u['id'], $grant);
        Http::flash('ok', $grant ? 'Autorização registrada.' : 'Autorização retirada. Publicações sobre você saem do site.');
        Http::redirect('/app/privacidade');
    }
}
