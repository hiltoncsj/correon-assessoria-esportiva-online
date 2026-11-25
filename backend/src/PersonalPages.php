<?php
declare(strict_types=1);
namespace Correon;

/** Telas do Personal Trainer para atleta, treinador e administrador. */
final class PersonalPages
{
    // ---- atleta (sempre o próprio usuário da sessão) ----

    public static function athlete(): never
    {
        $u = App::require('athlete');
        $packages = Db::all('SELECT p.*, t.name AS trainer FROM pt_packages p JOIN users t ON t.id = p.trainer_id WHERE p.athlete_id = ? ORDER BY p.status, p.id DESC', [$u['id']]);
        foreach ($packages as &$p) {
            $p['balance'] = Personal::balance($p);
            $p['slots'] = Db::all("SELECT weekday, start_time, end_time FROM trainer_availability WHERE trainer_id = ? AND mode = 'presencial' ORDER BY weekday, start_time", [$p['trainer_id']]);
        }
        unset($p);
        View::render('athlete/personal', ['title' => 'Personal Trainer', 'shell' => 'athlete', 'active' => 'personal', 'packages' => $packages,
            'sessions' => Db::all('SELECT s.*, t.name AS trainer, p.location FROM pt_sessions s JOIN users t ON t.id = s.trainer_id JOIN pt_packages p ON p.id = s.package_id
                                   WHERE s.athlete_id = ? ORDER BY s.starts_at DESC LIMIT 60', [$u['id']])]);
    }

    public static function athleteRequest(): never
    {
        $u = App::require('athlete');
        $err = Personal::request((int) $u['id'], (int) Http::input('package_id'), Http::input('date'), Http::input('time'));
        Http::flash($err === null ? 'ok' : 'err', $err ?? 'Pedido enviado. O horário só vale depois que o treinador confirmar.');
        Http::redirect('/app/personal');
    }

    public static function athleteCancel(int $sessionId): never
    {
        $u = App::require('athlete');
        $err = Personal::athleteCancel((int) $u['id'], $sessionId);
        Http::flash($err === null ? 'ok' : 'err', $err ?? 'Pedido cancelado.');
        Http::redirect('/app/personal');
    }

    // ---- treinador (somente as próprias sessões) ----

    public static function trainer(): never
    {
        $u = App::require('trainer');
        $t = (int) $u['id'];
        $base = 'SELECT s.*, a.name AS athlete, p.location, p.format FROM pt_sessions s JOIN users a ON a.id = s.athlete_id JOIN pt_packages p ON p.id = s.package_id WHERE s.trainer_id = ?';
        View::render('trainer/personal', ['title' => 'Personal Trainer', 'shell' => 'trainer', 'active' => 'personal',
            'pending' => Db::all("$base AND s.status = 'requested' ORDER BY s.starts_at", [$t]),
            'approved' => Db::all("$base AND s.status = 'approved' ORDER BY s.starts_at", [$t]),
            'history' => Db::all("$base AND s.status IN ('done','missed','cancelled','refused') ORDER BY s.starts_at DESC LIMIT 30", [$t]),
            'hasSlots' => Db::one("SELECT id FROM trainer_availability WHERE trainer_id = ? AND mode = 'presencial' LIMIT 1", [$t]) !== null]);
    }

    public static function trainerAction(int $sessionId): never
    {
        $u = App::require('trainer');
        if (!Db::one('SELECT id FROM pt_sessions WHERE id = ? AND trainer_id = ?', [$sessionId, $u['id']])) {
            App::notFound();
        }
        $action = Http::input('action');
        $err = Personal::trainerAction((int) $u['id'], $sessionId, $action, Http::input('consumes') === '1', Http::input('note'));
        Http::flash($err === null ? 'ok' : 'err', $err ?? 'Sessão atualizada.');
        Http::redirect('/treinador/personal');
    }

    // ---- administrador ----

    private static function athleteOr404(int $id): array
    {
        $u = Db::one("SELECT u.id, u.name FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.slug = 'athlete' WHERE u.id = ?", [$id]);
        if (!$u) {
            App::notFound();
        }
        return $u;
    }

    public static function adminList(): never
    {
        App::require('admin');
        $rows = Db::all('SELECT p.*, a.name AS athlete, t.name AS trainer FROM pt_packages p JOIN users a ON a.id = p.athlete_id JOIN users t ON t.id = p.trainer_id ORDER BY p.status, p.id DESC LIMIT 300');
        foreach ($rows as &$r) {
            $r['balance'] = Personal::balance($r);
        }
        unset($r);
        View::render('admin/pt_list', ['title' => 'Personal Trainer', 'shell' => 'admin', 'active' => 'personal', 'rows' => $rows]);
    }

    /** @param array<string,string> $old */
    private static function form(array $athlete, array $old, ?string $error, int $status): never
    {
        $trainers = Db::all("SELECT u.id, u.name FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.slug = 'trainer' WHERE u.status = 'active' ORDER BY u.name");
        View::render('admin/pt_new', ['title' => 'Novo Personal', 'shell' => 'admin', 'active' => 'personal', 'person' => $athlete, 'trainers' => $trainers, 'old' => $old, 'error' => $error], $status);
    }

    private const FIELDS = ['format', 'trainer_id', 'sessions_total', 'weekly_frequency', 'session_minutes', 'price', 'location', 'starts_on', 'valid_until', 'reason'];

    public static function adminNew(int $athleteId): never
    {
        App::require('admin');
        $old = array_fill_keys(self::FIELDS, '');
        $old['format'] = 'pacote';
        $old['starts_on'] = date('Y-m-d');
        self::form(self::athleteOr404($athleteId), $old, null, 200);
    }

    public static function adminCreate(int $athleteId): never
    {
        $me = App::require('admin');
        $athlete = self::athleteOr404($athleteId);
        $in = [];
        foreach (self::FIELDS as $k) {
            $in[$k] = Http::input($k);
        }
        $r = Personal::createPackage($athleteId, $in, (int) $me['id']);
        if ($r['error'] !== null) {
            self::form($athlete, $in, $r['error'], 422);
        }
        Http::flash('ok', 'Personal cadastrado.');
        Http::redirect('/admin/personal/' . $r['id']);
    }

    public static function adminPackage(int $id): never
    {
        App::require('admin');
        $p = Db::one('SELECT p.*, a.name AS athlete, t.name AS trainer FROM pt_packages p JOIN users a ON a.id = p.athlete_id JOIN users t ON t.id = p.trainer_id WHERE p.id = ?', [$id]);
        if (!$p) {
            App::notFound();
        }
        View::render('admin/pt', ['title' => 'Personal #' . $id, 'shell' => 'admin', 'active' => 'personal', 'p' => $p, 'balance' => Personal::balance($p),
            'sessions' => Db::all('SELECT * FROM pt_sessions WHERE package_id = ? ORDER BY starts_at DESC', [$id])]);
    }

    private static function backAdmin(int $id, ?string $error, string $ok): never
    {
        Http::flash($error === null ? 'ok' : 'err', $error ?? $ok);
        Http::redirect('/admin/personal/' . $id);
    }

    public static function adminCancel(int $id): never
    {
        $me = App::require('admin');
        self::backAdmin($id, Personal::cancelPackage($id, Http::input('reason'), (int) $me['id']), 'Pacote cancelado; sessões futuras foram canceladas sem descontar.');
    }

    public static function adminPay(int $id): never
    {
        $me = App::require('admin');
        self::backAdmin($id, Personal::pay($id, Http::input('paid_on'), Http::input('method'), (int) $me['id']), 'Pagamento registrado.');
    }
}
