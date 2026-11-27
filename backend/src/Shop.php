<?php
declare(strict_types=1);
namespace Correon;

/**
 * Loja de quem compra (atleta em /app, treinador em /treinador) e regras de pedido/estoque.
 * Não há gateway: o pagamento é feito por fora (Pix, link ou na entrega) e confirmado pela administração.
 * Nenhum dado de cartão passa pelo sistema. O pedido reserva o estoque; o cancelamento devolve.
 */
final class Shop
{
    public const METHODS = ['pix' => 'Pix', 'card_link' => 'Cartão (link de pagamento)', 'on_delivery' => 'Pagar na entrega'];
    private const MAX_QTY = 10;
    private const MAX_PENDING = 5; // pedidos sem pagamento ao mesmo tempo (evita prender estoque)

    /** @return array<string,mixed> */
    public static function settings(): array
    {
        return Db::one('SELECT * FROM shop_settings WHERE id = 1')
            ?? ['pix_key' => null, 'pix_holder' => null, 'card_link' => null, 'allow_on_delivery' => 1, 'instructions' => null];
    }

    /** Só são oferecidas as formas que a administração configurou. @return array<string,string> */
    public static function methods(): array
    {
        $s = self::settings();
        $on = ['pix' => (bool) $s['pix_key'], 'card_link' => (bool) $s['card_link'], 'on_delivery' => (bool) $s['allow_on_delivery']];
        return array_filter(self::METHODS, static fn ($k) => $on[$k], ARRAY_FILTER_USE_KEY);
    }

    /** @param array<string,mixed> $o */
    public static function state(array $o): string
    {
        if ($o['cancelled_at']) {
            return 'Cancelado';
        }
        $paid = $o['paid_at'] ? 'Pago' : 'Aguardando pagamento';
        return $o['delivered_at'] ? "$paid · Entregue" : $paid;
    }

    /** @return list<array<string,mixed>> */
    public static function items(int $orderId): array
    {
        return Db::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    /** Linhas do carrinho (só o que ainda está à venda). @return array{lines:list<array<string,mixed>>,total:int} */
    private static function cart(): array
    {
        $lines = [];
        $total = 0;
        foreach (($_SESSION['cart'] ?? []) as $variantId => $qty) {
            $v = Db::one('SELECT v.id, v.size, v.stock, p.name, p.price_cents FROM product_variants v JOIN products p ON p.id = v.product_id
                          WHERE v.id = ? AND v.active = 1 AND p.active = 1', [$variantId]);
            if (!$v) {
                unset($_SESSION['cart'][$variantId]);
                continue;
            }
            $line = $v + ['qty' => (int) $qty, 'subtotal' => (int) $v['price_cents'] * (int) $qty];
            $lines[] = $line;
            $total += $line['subtotal'];
        }
        return ['lines' => $lines, 'total' => $total];
    }

    /** Área pelo endereço; o perfil é exigido no servidor. @return array{0:array<string,mixed>,1:string,2:string} usuário, base e perfil */
    private static function ctx(): array
    {
        $trainer = str_starts_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/treinador/');
        $role = $trainer ? 'trainer' : 'athlete';
        return [App::require($role), $trainer ? '/treinador' : '/app', $role];
    }

    /** @param array<string,mixed> $vars */
    private static function page(string $view, string $title, string $active, array $vars): never
    {
        [, $base, $role] = self::ctx();
        View::render($view, ['title' => $title, 'shell' => $role, 'active' => $active, 'base' => $base,
            'label' => $role === 'trainer' ? 'Treinador' : 'Atleta'] + $vars);
    }

    public static function catalog(): never
    {
        self::ctx();
        $products = Db::all('SELECT * FROM products WHERE active = 1 ORDER BY name, id');
        foreach ($products as &$p) {
            $p['variants'] = Db::all('SELECT id, size, stock FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY id', [$p['id']]);
            $p['images'] = array_column(Db::all('SELECT id FROM product_images WHERE product_id = ? ORDER BY id', [$p['id']]), 'id');
        }
        unset($p);
        self::page('shop/catalog', 'Loja', 'loja', ['products' => $products,
            'inCart' => array_sum($_SESSION['cart'] ?? [])]);
    }

    public static function image(int $imageId): never
    {
        self::ctx();
        $i = Db::one('SELECT i.media_id FROM product_images i JOIN products p ON p.id = i.product_id WHERE i.id = ? AND p.active = 1', [$imageId]);
        if (!$i) {
            App::notFound();
        }
        Media::output((int) $i['media_id'], false);
    }

    public static function add(): never
    {
        [, $base] = self::ctx();
        $id = Http::input('variant_id');
        $qty = Http::input('qty');
        $v = ctype_digit($id) ? Db::one('SELECT v.id, v.stock FROM product_variants v JOIN products p ON p.id = v.product_id
                                        WHERE v.id = ? AND v.active = 1 AND p.active = 1', [$id]) : null;
        if (!$v || !ctype_digit($qty) || (int) $qty < 1) {
            Http::flash('err', 'Escolha o tamanho e a quantidade.');
            Http::redirect($base . '/loja');
        }
        $new = (int) ($_SESSION['cart'][$v['id']] ?? 0) + (int) $qty;
        if ($new > min(self::MAX_QTY, (int) $v['stock'])) {
            Http::flash('err', 'Quantidade indisponível. Máximo para este tamanho: ' . min(self::MAX_QTY, (int) $v['stock']) . '.');
            Http::redirect($base . '/loja');
        }
        $_SESSION['cart'][(int) $v['id']] = $new;
        Http::flash('ok', 'Adicionado ao carrinho.');
        Http::redirect($base . '/loja');
    }

    public static function viewCart(): never
    {
        self::ctx();
        self::page('shop/cart', 'Carrinho', 'loja', ['cart' => self::cart(), 'methods' => self::methods()]);
    }

    public static function removeFromCart(): never
    {
        [, $base] = self::ctx();
        unset($_SESSION['cart'][(int) Http::input('variant_id')]);
        Http::redirect($base . '/loja/carrinho');
    }

    public static function checkout(): never
    {
        [$u, $base] = self::ctx();
        $athlete = (int) $u['id'];
        $cart = self::cart();
        $method = Http::input('method');
        $back = static function (string $msg) use ($base): never {
            Http::flash('err', $msg);
            Http::redirect($base . '/loja/carrinho');
        };
        if (!$cart['lines']) {
            $back('O carrinho está vazio.');
        }
        if (!isset(self::methods()[$method])) {
            $back('Escolha a forma de pagamento.');
        }
        if (Http::input('total') !== (string) $cart['total']) {
            $back('Os preços mudaram. Confira o total e confirme de novo.');
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            // trava o atleta: dois envios simultâneos não furam o limite de pedidos
            Db::one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$athlete]);
            if ((int) Db::one('SELECT COUNT(*) c FROM orders WHERE athlete_id = ? AND paid_at IS NULL AND delivered_at IS NULL AND cancelled_at IS NULL', [$athlete])['c'] >= self::MAX_PENDING) {
                $pdo->rollBack();
                $back('Você já tem ' . self::MAX_PENDING . ' pedidos aguardando pagamento.');
            }
            Db::run('INSERT INTO orders (athlete_id, payment_method, total_cents) VALUES (?,?,?)', [$athlete, $method, $cart['total']]);
            $order = (int) $pdo->lastInsertId();
            foreach ($cart['lines'] as $l) {
                // baixa condicional: nunca vende além do estoque
                $done = Db::run('UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?', [$l['qty'], $l['id'], $l['qty']])->rowCount();
                if ($done !== 1) {
                    $pdo->rollBack();
                    $back("Estoque insuficiente para {$l['name']} ({$l['size']}). Ajuste o carrinho.");
                }
                Db::run('INSERT INTO order_items (order_id, variant_id, product_name, size, unit_cents, qty) VALUES (?,?,?,?,?,?)',
                    [$order, $l['id'], $l['name'], $l['size'], $l['price_cents'], $l['qty']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        unset($_SESSION['cart']);
        Audit::log('order_created', $athlete, 'order', $order, ['total' => $cart['total'], 'method' => $method]);
        Http::flash('ok', 'Pedido registrado. Veja abaixo como pagar.');
        Http::redirect($base . '/pedidos/' . $order);
    }

    public static function myOrders(): never
    {
        [$u, $base] = self::ctx();
        self::page('shop/orders', 'Meus pedidos', 'pedidos', [
            'orders' => Db::all('SELECT * FROM orders WHERE athlete_id = ? ORDER BY id DESC LIMIT 100', [$u['id']])]);
    }

    /** @return array<string,mixed> pedido do próprio atleta, ou 404 */
    private static function ownOrder(int $id, int $athleteId): array
    {
        $o = Db::one('SELECT * FROM orders WHERE id = ? AND athlete_id = ?', [$id, $athleteId]);
        if (!$o) {
            App::notFound();
        }
        return $o;
    }

    public static function myOrder(int $id): never
    {
        [$u, $base] = self::ctx();
        self::page('shop/order', "Pedido #$id", 'pedidos', [
            'order' => self::ownOrder($id, (int) $u['id']), 'items' => self::items($id), 'settings' => self::settings()]);
    }

    public static function cancelMine(int $id): never
    {
        [$u, $base] = self::ctx();
        self::ownOrder($id, (int) $u['id']);
        $ok = self::cancel($id, (int) $u['id']);
        Http::flash($ok ? 'ok' : 'err', $ok ? 'Pedido cancelado.' : 'Este pedido não pode mais ser cancelado por aqui. Fale com a equipe.');
        Http::redirect($base . '/pedidos/' . $id);
    }

    /** Cancela pedido não pago e não entregue e devolve o estoque (uma única vez). */
    public static function cancel(int $orderId, int $byUser): bool
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $done = Db::run('UPDATE orders SET cancelled_at = NOW(), cancelled_by = ? WHERE id = ? AND cancelled_at IS NULL AND paid_at IS NULL AND delivered_at IS NULL', [$byUser, $orderId])->rowCount();
            if ($done === 1) {
                foreach (self::items($orderId) as $i) {
                    Db::run('UPDATE product_variants SET stock = stock + ? WHERE id = ?', [$i['qty'], $i['variant_id']]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        if ($done === 1) {
            Audit::log('order_cancelled', $byUser, 'order', $orderId);
        }
        return $done === 1;
    }
}
