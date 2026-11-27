<?php
declare(strict_types=1);
namespace Correon;

/**
 * Regras comerciais: preços, contratos, parcelas e pagamentos (baixa manual).
 * Valores em centavos. Nada é apagado: cancelar/estornar muda a situação e grava histórico.
 * NÃO há gateway, cobrança automática, suspensão por atraso nem multa (regras ainda não definidas).
 */
final class Billing
{
    public const PRODUCTS = [
        'corrida' => 'Corrida', 'triathlon' => 'Triathlon', 'musculacao' => 'Musculação',
        'corrida+musculacao' => 'Corrida + Musculação', 'triathlon+musculacao' => 'Triathlon + Musculação',
    ];
    public const PERIODS = ['mensal' => 'Mensal', 'trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual'];
    public const MONTHS = ['mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12];
    public const CONDITIONS = ['standard' => 'Preço de tabela', 'legacy_120' => 'Aluno antigo (R$ 120/mês)', 'custom' => 'Valor negociado'];
    public const METHODS = ['pix' => 'Pix', 'cartao' => 'Cartão', 'boleto' => 'Boleto', 'dinheiro' => 'Dinheiro', 'transferencia' => 'Transferência', 'outro' => 'Outro'];
    public const LEGACY_CENTS = 12000;

    public static function money(int|string|null $cents): string
    {
        return $cents === null ? '—' : 'R$ ' . number_format(((int) $cents) / 100, 2, ',', '.');
    }

    /** "1.440,00", "180", "180,5" -> centavos; null se inválido. */
    public static function parseMoney(string $s): ?int
    {
        $s = trim(str_replace(['R$', ' '], '', $s));
        if (!preg_match('/^(\d{1,3}(\.\d{3})+|\d+)(,\d{1,2})?$/', $s)) {
            return null;
        }
        $cents = (int) round((float) str_replace(['.', ','], ['', '.'], $s) * 100);
        return $cents > 0 && $cents <= 10_000_000 ? $cents : null;
    }

    public static function validDate(string $d): bool
    {
        $x = \DateTimeImmutable::createFromFormat('!Y-m-d', $d);
        return $x !== false && $x->format('Y-m-d') === $d;
    }

    /** Soma meses sem estourar o fim do mês (31/01 + 1 mês = 28/02). */
    public static function addMonths(string $ymd, int $months): string
    {
        $d = new \DateTimeImmutable($ymd);
        $first = $d->modify('first day of this month')->modify("+$months months");
        $day = min((int) $d->format('j'), (int) $first->format('t'));
        return $first->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    }

    public static function endDate(string $start, string $period): string
    {
        return (new \DateTimeImmutable(self::addMonths($start, self::MONTHS[$period])))->modify('-1 day')->format('Y-m-d');
    }

    /** @return list<string> modalidades cobertas pelo produto */
    public static function modalitiesOf(string $product): array
    {
        return explode('+', $product);
    }

    /** @return array<string,array<string,array{total:int|null,max:int}>> */
    public static function priceTable(): array
    {
        $out = [];
        foreach (Db::all('SELECT product, period, total_cents, max_installments FROM plan_prices') as $r) {
            $out[$r['product']][$r['period']] = ['total' => $r['total_cents'] === null ? null : (int) $r['total_cents'], 'max' => (int) $r['max_installments']];
        }
        return $out;
    }

    /** Divide o total em parcelas; os centavos que sobram vão para a última. @return list<int> */
    public static function split(int $total, int $n): array
    {
        $base = intdiv($total, $n);
        $parts = array_fill(0, $n, $base);
        $parts[$n - 1] += $total - $base * $n;
        return $parts;
    }

    /**
     * Conflitos com contratos ativos no mesmo intervalo: mesma modalidade, ou Corrida junto com Triathlon.
     * @return string|null mensagem do conflito
     */
    public static function conflict(int $athleteId, string $product, string $start, string $end, ?int $ignoreId = null): ?string
    {
        $new = self::modalitiesOf($product);
        $rows = Db::all("SELECT id, product FROM contracts WHERE athlete_id = ? AND status = 'active' AND starts_on <= ? AND ends_on >= ?", [$athleteId, $end, $start]);
        foreach ($rows as $r) {
            if ((int) $r['id'] === $ignoreId) {
                continue;
            }
            $old = self::modalitiesOf($r['product']);
            if (array_intersect($new, $old)) {
                return 'Já existe contrato ativo com essa modalidade no período (contrato #' . $r['id'] . ').';
            }
            $both = array_merge($new, $old);
            if (in_array('corrida', $both, true) && in_array('triathlon', $both, true)) {
                return 'Corrida e Triathlon não se combinam (contrato #' . $r['id'] . '). Use "Trocar modalidade".';
            }
        }
        return null;
    }

    public static function event(int $contractId, string $event, ?int $by, ?string $details = null, ?int $chargeId = null): void
    {
        Db::run('INSERT INTO contract_events (contract_id, charge_id, event, details, by_user) VALUES (?,?,?,?,?)',
            [$contractId, $chargeId, $event, $details === null ? null : mb_substr($details, 0, 400), $by]);
    }

    /**
     * Valida e cria o contrato com suas parcelas.
     * @param array{product:string,period:string,condition:string,value:string,reason:string,installments:string,starts_on:string,first_due:string} $in
     * @return array{id:int|null,error:string|null}
     */
    public static function create(int $athleteId, array $in, int $adminId): array
    {
        $fail = static fn (string $m) => ['id' => null, 'error' => $m];
        $product = $in['product'];
        $period = $in['period'];
        $cond = $in['condition'];
        if (!isset(self::PRODUCTS[$product], self::PERIODS[$period], self::CONDITIONS[$cond])) {
            return $fail('Escolha o produto, o período e a condição.');
        }
        if (!self::validDate($in['starts_on']) || $in['starts_on'] < date('Y-m-d', strtotime('-1 year')) || $in['starts_on'] > date('Y-m-d', strtotime('+1 year'))) {
            return $fail('Data de início inválida.');
        }
        $firstDue = $in['first_due'] !== '' ? $in['first_due'] : $in['starts_on'];
        if (!self::validDate($firstDue) || $firstDue < date('Y-m-d', strtotime($in['starts_on'] . ' -31 days')) || $firstDue > date('Y-m-d', strtotime($in['starts_on'] . ' +62 days'))) {
            return $fail('Data do primeiro vencimento inválida.');
        }
        $reason = trim($in['reason']);
        if ($cond === 'standard') {
            $row = self::priceTable()[$product][$period] ?? null;
            if (!$row || $row['total'] === null) {
                return $fail('Não há preço de tabela para este produto e período. Cadastre o preço ou use "Valor negociado".');
            }
            [$price, $max] = [$row['total'], $row['max']];
            $reason = '';
            // promoção em vigor no momento da criação: vale só para o anual a preço de tabela
            if ($period === 'anual' && ($promo = Promo::annualDiscount()) !== null) {
                $price = Promo::discounted($price, $promo['pct']);
                $reason = 'Promoção "' . $promo['title'] . '": ' . Promo::pctLabel($promo['pct']) . '% de desconto no anual';
            }
        } elseif ($cond === 'legacy_120') {
            if ($period !== 'mensal' || count(self::modalitiesOf($product)) !== 1) {
                return $fail('A condição de aluno antigo (R$ 120) vale para o mensal de uma modalidade. Outros casos: use "Valor negociado" e explique.');
            }
            [$price, $max] = [self::LEGACY_CENTS, 1];
        } else {
            $price = self::parseMoney($in['value']);
            if ($price === null) {
                return $fail('Informe o valor total negociado (ex.: 150,00).');
            }
            if (mb_strlen($reason) < 5) {
                return $fail('Valor negociado exige uma justificativa.');
            }
            $max = self::MONTHS[$period];
        }
        $n = $in['installments'] === '' ? 1 : (ctype_digit($in['installments']) ? (int) $in['installments'] : 0);
        if ($n < 1 || $n > $max) {
            return $fail("Número de parcelas entre 1 e $max.");
        }
        $end = self::endDate($in['starts_on'], $period);

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$athleteId]); // evita dois contratos simultâneos
            if (($c = self::conflict($athleteId, $product, $in['starts_on'], $end)) !== null) {
                $pdo->rollBack();
                return $fail($c);
            }
            $id = self::insert($athleteId, $product, $period, $price, $n, $cond, $reason === '' ? null : mb_substr($reason, 0, 300),
                $in['starts_on'], $end, $firstDue, $adminId, null);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::log('contract_created', $adminId, 'contract', $id, ['athlete_id' => $athleteId, 'condition' => $cond]);
        return ['id' => $id, 'error' => null];
    }

    private static function insert(int $athleteId, string $product, string $period, int $price, int $n, string $cond, ?string $reason,
        string $start, string $end, string $firstDue, int $adminId, ?int $renewedFrom): int
    {
        Db::run('INSERT INTO contracts (athlete_id, product, period, price_cents, installments, price_condition, condition_reason, starts_on, ends_on, renewed_from, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$athleteId, $product, $period, $price, $n, $cond, $reason, $start, $end, $renewedFrom, $adminId]);
        $id = (int) Db::pdo()->lastInsertId();
        foreach (self::split($price, $n) as $i => $amount) {
            Db::run('INSERT INTO charges (contract_id, seq, due_on, amount_cents) VALUES (?,?,?,?)', [$id, $i + 1, self::addMonths($firstDue, $i), $amount]);
        }
        self::event($id, $renewedFrom ? 'renewed' : 'created', $adminId,
            self::PRODUCTS[$product] . ' ' . self::PERIODS[$period] . ' ' . self::money($price) . " em {$n}x" . ($renewedFrom ? " (renovação do #$renewedFrom)" : ''));
        return $id;
    }

    /**
     * Renova mantendo produto, período, VALOR e condição (aluno antigo continua antigo).
     * Exceção: contrato feito com desconto de promoção volta ao preço de tabela atual.
     */
    public static function renew(int $contractId, int $adminId): array
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $c = Db::one('SELECT * FROM contracts WHERE id = ? FOR UPDATE', [$contractId]);
            $error = null;
            if (!$c || $c['status'] !== 'active') {
                $error = 'Só um contrato ativo pode ser renovado.';
            } elseif (Db::one('SELECT id FROM contracts WHERE renewed_from = ?', [$contractId])) {
                $error = 'Este contrato já foi renovado.';
            }
            if ($error === null) {
                $start = date('Y-m-d', strtotime($c['ends_on'] . ' +1 day'));
                $end = self::endDate($start, $c['period']);
                $error = self::conflict((int) $c['athlete_id'], $c['product'], $start, $end);
            }
            if ($error === null) {
                [$price, $n, $reason] = [(int) $c['price_cents'], (int) $c['installments'], $c['condition_reason']];
                // só a promoção grava justificativa em contrato a preço de tabela
                if ($c['price_condition'] === 'standard' && $reason !== null) {
                    $row = self::priceTable()[$c['product']][$c['period']] ?? null;
                    if (!$row || $row['total'] === null) {
                        $error = 'Este contrato foi feito em promoção e não há preço de tabela para renovar. Cadastre o preço em Preços.';
                    } else {
                        [$price, $n, $reason] = [$row['total'], min($n, $row['max']), null];
                    }
                }
            }
            if ($error !== null) {
                $pdo->rollBack();
                return ['id' => null, 'error' => $error];
            }
            $id = self::insert((int) $c['athlete_id'], $c['product'], $c['period'], $price, $n,
                $c['price_condition'], $reason, $start, $end, $start, $adminId, $contractId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::log('contract_renewed', $adminId, 'contract', $id, ['from' => $contractId]);
        return ['id' => $id, 'error' => null];
    }

    /** Cancela o contrato e as parcelas em aberto; parcelas pagas permanecem. */
    public static function cancel(int $contractId, string $reason, int $adminId): ?string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            return 'Informe o motivo do cancelamento.';
        }
        $st = Db::run("UPDATE contracts SET status = 'cancelled', cancelled_at = NOW(), cancel_reason = ? WHERE id = ? AND status = 'active'",
            [mb_substr($reason, 0, 300), $contractId]);
        if ($st->rowCount() !== 1) {
            return 'Este contrato não está ativo.';
        }
        Db::run("UPDATE charges SET status = 'cancelled' WHERE contract_id = ? AND status = 'open'", [$contractId]);
        self::event($contractId, 'cancelled', $adminId, $reason);
        Audit::log('contract_cancelled', $adminId, 'contract', $contractId);
        return null;
    }

    /** Troca Corrida <-> Triathlon mantendo o valor. */
    public static function swap(int $contractId, int $adminId): ?string
    {
        $c = Db::one("SELECT * FROM contracts WHERE id = ? AND status = 'active'", [$contractId]);
        if (!$c) {
            return 'Este contrato não está ativo.';
        }
        $parts = self::modalitiesOf($c['product']);
        if (in_array('corrida', $parts, true)) {
            $new = str_replace('corrida', 'triathlon', $c['product']);
        } elseif (in_array('triathlon', $parts, true)) {
            $new = str_replace('triathlon', 'corrida', $c['product']);
        } else {
            return 'A troca vale apenas entre Corrida e Triathlon.';
        }
        if (($err = self::conflict((int) $c['athlete_id'], $new, $c['starts_on'], $c['ends_on'], $contractId)) !== null) {
            return $err;
        }
        Db::run('UPDATE contracts SET product = ? WHERE id = ?', [$new, $contractId]);
        self::event($contractId, 'swapped', $adminId, self::PRODUCTS[$c['product']] . ' → ' . self::PRODUCTS[$new] . ' (mesmo valor)');
        Audit::log('contract_swapped', $adminId, 'contract', $contractId);
        return null;
    }

    /** Baixa manual. Condicional (status = open): repetir a ação não paga duas vezes. */
    public static function pay(int $chargeId, string $paidOn, string $method, int $adminId): ?string
    {
        if (!self::validDate($paidOn) || $paidOn > date('Y-m-d') || $paidOn < date('Y-m-d', strtotime('-2 years'))) {
            return 'Data de pagamento inválida (não pode ser futura).';
        }
        if (!isset(self::METHODS[$method])) {
            return 'Escolha a forma de pagamento.';
        }
        $ch = Db::one('SELECT contract_id, seq, amount_cents FROM charges WHERE id = ?', [$chargeId]);
        $st = Db::run("UPDATE charges SET status = 'paid', paid_on = ?, method = ? WHERE id = ? AND status = 'open'", [$paidOn, $method, $chargeId]);
        if (!$ch || $st->rowCount() !== 1) {
            return 'Esta parcela não está em aberto.';
        }
        self::event((int) $ch['contract_id'], 'paid', $adminId, "Parcela {$ch['seq']}: " . self::money($ch['amount_cents']) . ' por ' . self::METHODS[$method] . ' em ' . date('d/m/Y', strtotime($paidOn)), $chargeId);
        Audit::log('payment_registered', $adminId, 'charge', $chargeId);
        return null;
    }

    public static function reverse(int $chargeId, string $reason, int $adminId): ?string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            return 'Informe o motivo do estorno.';
        }
        $ch = Db::one('SELECT c.contract_id, c.seq, k.status AS contract_status FROM charges c JOIN contracts k ON k.id = c.contract_id WHERE c.id = ?', [$chargeId]);
        if (!$ch) {
            return 'Parcela não encontrada.';
        }
        $to = $ch['contract_status'] === 'active' ? 'open' : 'cancelled';
        $st = Db::run("UPDATE charges SET status = ?, paid_on = NULL, method = NULL WHERE id = ? AND status = 'paid'", [$to, $chargeId]);
        if ($st->rowCount() !== 1) {
            return 'Esta parcela não está paga.';
        }
        self::event((int) $ch['contract_id'], 'reversed', $adminId, "Estorno da parcela {$ch['seq']}: $reason", $chargeId);
        Audit::log('payment_reversed', $adminId, 'charge', $chargeId);
        return null;
    }

    /** Situação exibida (derivada das datas). */
    public static function label(array $c): string
    {
        if ($c['status'] === 'cancelled') {
            return 'Cancelado';
        }
        $today = date('Y-m-d');
        return $c['ends_on'] < $today ? 'Vencido' : ($c['starts_on'] > $today ? 'A iniciar' : 'Vigente');
    }

    /** @return list<array<string,mixed>> contratos do atleta com resumo das parcelas */
    public static function ofAthlete(int $athleteId): array
    {
        return Db::all(
            "SELECT k.*,
               (SELECT COUNT(*) FROM charges c WHERE c.contract_id = k.id AND c.status = 'paid') AS paid,
               (SELECT COUNT(*) FROM charges c WHERE c.contract_id = k.id AND c.status = 'open' AND c.due_on < CURDATE()) AS overdue
             FROM contracts k WHERE k.athlete_id = ? ORDER BY k.status, k.starts_on DESC, k.id DESC",
            [$athleteId]
        );
    }
}
