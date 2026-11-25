<?php
declare(strict_types=1);
namespace Correon;

/** Telas administrativas de preços, contratos, pagamentos e financeiro. Somente perfil admin. */
final class Finance
{
    private static function admin(): array
    {
        return App::require('admin');
    }

    public static function prices(): never
    {
        self::admin();
        View::render('admin/prices', ['title' => 'Preços', 'shell' => 'admin', 'active' => 'precos', 'table' => Billing::priceTable()]);
    }

    /** price[produto][período] = valor ("" = não vendido) e max[produto][período] */
    public static function savePrices(): never
    {
        $me = self::admin();
        $current = Billing::priceTable();
        $changes = [];
        foreach (Billing::PRODUCTS as $product => $label) {
            foreach (Billing::PERIODS as $period => $periodLabel) {
                $raw = $_POST['price'][$product][$period] ?? null;
                $rawMax = $_POST['max'][$product][$period] ?? null;
                if (!is_string($raw) || !is_string($rawMax)) {
                    continue;
                }
                $total = trim($raw) === '' ? null : Billing::parseMoney($raw);
                $max = ctype_digit($rawMax) ? (int) $rawMax : 0;
                if ((trim($raw) !== '' && $total === null) || $max < 1 || $max > Billing::MONTHS[$period]) {
                    Http::flash('err', "Valor ou parcelas inválidos em $label $periodLabel (parcelas de 1 a " . Billing::MONTHS[$period] . '). Nada foi salvo.');
                    Http::redirect('/admin/precos');
                }
                $old = $current[$product][$period] ?? ['total' => null, 'max' => 1];
                if ($old['total'] !== $total || $old['max'] !== $max) {
                    $changes[] = [$product, $period, $total, $max, $old['total']];
                }
            }
        }
        foreach ($changes as [$product, $period, $total, $max, $before]) {
            Db::run('INSERT INTO plan_prices (product, period, total_cents, max_installments, updated_by) VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE total_cents = VALUES(total_cents), max_installments = VALUES(max_installments), updated_by = VALUES(updated_by)',
                [$product, $period, $total, $max, $me['id']]);
            Audit::log('price_changed', (int) $me['id'], 'price', null, ['product' => $product, 'period' => $period, 'before' => $before, 'after' => $total, 'max' => $max]);
        }
        Http::flash('ok', $changes ? count($changes) . ' preço(s) atualizado(s). Contratos existentes não mudam. A página pública precisa ser atualizada à parte.' : 'Nenhuma alteração.');
        Http::redirect('/admin/precos');
    }

    public static function contracts(): never
    {
        self::admin();
        $filter = (string) (is_string($_GET['filtro'] ?? null) ? $_GET['filtro'] : 'ativos');
        $where = [
            'ativos' => "k.status = 'active' AND k.ends_on >= CURDATE()",
            'atrasados' => "EXISTS (SELECT 1 FROM charges c WHERE c.contract_id = k.id AND c.status = 'open' AND c.due_on < CURDATE())",
            'vencendo' => "k.status = 'active' AND k.ends_on BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE() + INTERVAL 7 DAY AND NOT EXISTS (SELECT 1 FROM contracts n WHERE n.renewed_from = k.id)",
            'todos' => '1 = 1',
        ];
        $filter = isset($where[$filter]) ? $filter : 'ativos';
        View::render('admin/contracts', ['title' => 'Contratos', 'shell' => 'admin', 'active' => 'contratos', 'filter' => $filter,
            'rows' => Db::all(
                "SELECT k.*, a.name AS athlete,
                   (SELECT COUNT(*) FROM charges c WHERE c.contract_id = k.id AND c.status = 'open' AND c.due_on < CURDATE()) AS overdue
                 FROM contracts k JOIN users a ON a.id = k.athlete_id WHERE {$where[$filter]} ORDER BY k.ends_on, a.name LIMIT 500"
            )]);
    }

    /** @return array<string,mixed> atleta ou 404 */
    private static function athlete(int $id): array
    {
        $u = Db::one("SELECT u.id, u.name, u.status FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.slug = 'athlete' WHERE u.id = ?", [$id]);
        if (!$u) {
            App::notFound();
        }
        return $u;
    }

    /** @param array<string,string> $old */
    private static function contractForm(array $athlete, array $old, ?string $error, int $status): never
    {
        View::render('admin/contract_new', ['title' => 'Novo contrato', 'shell' => 'admin', 'active' => 'atletas',
            'person' => $athlete, 'old' => $old, 'error' => $error, 'table' => Billing::priceTable()], $status);
    }

    public static function newContract(int $athleteId): never
    {
        self::admin();
        self::contractForm(self::athlete($athleteId), ['product' => '', 'period' => 'mensal', 'condition' => 'standard', 'value' => '', 'reason' => '',
            'installments' => '1', 'starts_on' => date('Y-m-d'), 'first_due' => ''], null, 200);
    }

    public static function createContract(int $athleteId): never
    {
        $me = self::admin();
        $athlete = self::athlete($athleteId);
        $in = [];
        foreach (['product', 'period', 'condition', 'value', 'reason', 'installments', 'starts_on', 'first_due'] as $k) {
            $in[$k] = Http::input($k);
        }
        $r = Billing::create($athleteId, $in, (int) $me['id']);
        if ($r['error'] !== null) {
            self::contractForm($athlete, $in, $r['error'], 422);
        }
        Http::flash('ok', 'Contrato criado.');
        Http::redirect('/admin/contratos/' . $r['id']);
    }

    public static function contract(int $id): never
    {
        self::admin();
        $c = Db::one('SELECT k.*, a.name AS athlete FROM contracts k JOIN users a ON a.id = k.athlete_id WHERE k.id = ?', [$id]);
        if (!$c) {
            App::notFound();
        }
        View::render('admin/contract', ['title' => 'Contrato #' . $id, 'shell' => 'admin', 'active' => 'contratos', 'c' => $c,
            'charges' => Db::all('SELECT * FROM charges WHERE contract_id = ? ORDER BY seq', [$id]),
            'events' => Db::all('SELECT e.event, e.details, e.created_at, u.name AS who FROM contract_events e LEFT JOIN users u ON u.id = e.by_user WHERE e.contract_id = ? ORDER BY e.id DESC', [$id]),
            'renewal' => Db::one('SELECT id FROM contracts WHERE renewed_from = ?', [$id])]);
    }

    private static function back(int $contractId, ?string $error, string $ok): never
    {
        Http::flash($error === null ? 'ok' : 'err', $error ?? $ok);
        Http::redirect('/admin/contratos/' . $contractId);
    }

    public static function cancel(int $id): never
    {
        $me = self::admin();
        self::back($id, Billing::cancel($id, Http::input('reason'), (int) $me['id']), 'Contrato cancelado. Parcelas pagas foram mantidas.');
    }

    public static function renew(int $id): never
    {
        $me = self::admin();
        $r = Billing::renew($id, (int) $me['id']);
        self::back($r['id'] ?? $id, $r['error'], 'Contrato renovado com o mesmo valor e condição.');
    }

    public static function swap(int $id): never
    {
        $me = self::admin();
        self::back($id, Billing::swap($id, (int) $me['id']), 'Modalidade trocada, com o mesmo valor. Ajuste o treinador do atleta, se preciso.');
    }

    private static function contractOfCharge(int $chargeId): int
    {
        $r = Db::one('SELECT contract_id FROM charges WHERE id = ?', [$chargeId]);
        if (!$r) {
            App::notFound();
        }
        return (int) $r['contract_id'];
    }

    public static function pay(int $chargeId): never
    {
        $me = self::admin();
        $contract = self::contractOfCharge($chargeId);
        self::back($contract, Billing::pay($chargeId, Http::input('paid_on'), Http::input('method'), (int) $me['id']), 'Pagamento registrado.');
    }

    public static function reverse(int $chargeId): never
    {
        $me = self::admin();
        $contract = self::contractOfCharge($chargeId);
        self::back($contract, Billing::reverse($chargeId, Http::input('reason'), (int) $me['id']), 'Pagamento estornado.');
    }

    /** Números reais a partir das parcelas. */
    public static function overview(): never
    {
        self::admin();
        $month = (string) (is_string($_GET['mes'] ?? null) ? $_GET['mes'] : '');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        View::render('admin/finance', ['title' => 'Financeiro', 'shell' => 'admin', 'active' => 'financeiro', 'month' => $month,
            'totals' => Db::one(
                "SELECT
                   (SELECT COALESCE(SUM(amount_cents),0) FROM charges WHERE status = 'paid' AND paid_on BETWEEN ? AND ?) AS received,
                   (SELECT COALESCE(SUM(amount_cents),0) FROM charges WHERE status <> 'cancelled' AND due_on BETWEEN ? AND ?) AS due,
                   (SELECT COALESCE(SUM(amount_cents),0) FROM charges WHERE status = 'open' AND due_on < CURDATE()) AS overdue,
                   (SELECT COUNT(DISTINCT athlete_id) FROM contracts WHERE status = 'active' AND starts_on <= CURDATE() AND ends_on >= CURDATE()) AS athletes",
                [$from, $to, $from, $to]
            ),
            'overdue' => Db::all(
                "SELECT c.id, c.due_on, c.amount_cents, c.seq, k.id AS contract_id, a.name AS athlete, DATEDIFF(CURDATE(), c.due_on) AS days
                 FROM charges c JOIN contracts k ON k.id = c.contract_id JOIN users a ON a.id = k.athlete_id
                 WHERE c.status = 'open' AND c.due_on < CURDATE() ORDER BY c.due_on LIMIT 200"
            )]);
    }
}
