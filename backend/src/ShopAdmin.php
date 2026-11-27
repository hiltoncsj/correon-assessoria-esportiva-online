<?php
declare(strict_types=1);
namespace Correon;

/** Loja no painel do administrador: produtos, estoque, pagamento, pedidos e relatório de vendas. */
final class ShopAdmin
{
    private const SIZE_ROWS = 6; // linhas em branco para novos tamanhos
    private const MAX_IMAGES = 8;

    private static function admin(): array
    {
        return App::require('admin');
    }

    /** Pedidos em aberto (sem pagamento ou sem entrega), para o menu. */
    public static function openCount(): int
    {
        return (int) Db::one('SELECT COUNT(*) c FROM orders WHERE cancelled_at IS NULL AND (paid_at IS NULL OR delivered_at IS NULL)')['c'];
    }

    public static function products(): never
    {
        self::admin();
        View::render('shop/admin_products', ['title' => 'Loja', 'shell' => 'admin', 'active' => 'loja', 'settings' => Shop::settings(),
            'products' => Db::all(
                'SELECT p.*, (SELECT GROUP_CONCAT(CONCAT(v.size, \': \', v.stock) ORDER BY v.id SEPARATOR \' · \') FROM product_variants v WHERE v.product_id = p.id AND v.active = 1) AS sizes
                 FROM products p WHERE p.deleted_at IS NULL ORDER BY p.active DESC, p.name'
            )]);
    }

    private static function validUrl(string $url): bool
    {
        return mb_strlen($url) <= 500 && filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($url), 'https://');
    }

    public static function saveSettings(): never
    {
        $me = self::admin();
        $v = [];
        foreach (['pix_key' => 140, 'pix_holder' => 120, 'card_link' => 500, 'instructions' => 1000] as $k => $max) {
            $v[$k] = Http::input($k);
            if (mb_strlen($v[$k]) > $max) {
                Http::flash('err', 'Um dos campos de pagamento está longo demais. Nada foi salvo.');
                Http::redirect('/admin/loja');
            }
        }
        if ($v['card_link'] !== '' && !self::validUrl($v['card_link'])) {
            Http::flash('err', 'O link de pagamento precisa ser um endereço https://. Nada foi salvo.');
            Http::redirect('/admin/loja');
        }
        $n = static fn (string $s) => $s === '' ? null : $s;
        $row = [$n($v['pix_key']), $n($v['pix_holder']), $n($v['card_link']), Http::input('allow_on_delivery') === '1' ? 1 : 0, $n($v['instructions'])];
        Db::run('INSERT INTO shop_settings (id, pix_key, pix_holder, card_link, allow_on_delivery, instructions) VALUES (1,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE pix_key = VALUES(pix_key), pix_holder = VALUES(pix_holder), card_link = VALUES(card_link),
                 allow_on_delivery = VALUES(allow_on_delivery), instructions = VALUES(instructions)', $row);
        // dados de recebimento: toda mudança fica auditada
        Audit::log('shop_payment_settings_changed', (int) $me['id'], 'shop', 1, ['pix_key' => $row[0], 'card_link' => $row[2]]);
        Http::flash('ok', 'Formas de pagamento salvas.');
        Http::redirect('/admin/loja');
    }

    /** @param array<string,mixed> $old */
    private static function form(?int $id, array $old, ?string $error, int $status): never
    {
        View::render('shop/admin_product', ['title' => $id ? 'Editar produto' : 'Novo produto', 'shell' => 'admin', 'active' => 'loja',
            'id' => $id, 'old' => $old, 'error' => $error, 'blankRows' => self::SIZE_ROWS,
            'variants' => $id ? Db::all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY id', [$id]) : [],
            'images' => $id ? self::images($id) : [], 'maxImages' => self::MAX_IMAGES], $status);
    }

    /** @return array<string,mixed> */
    private static function product(int $id): array
    {
        $p = Db::one('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$p) {
            App::notFound();
        }
        return $p;
    }

    public static function newProduct(): never
    {
        self::admin();
        self::form(null, ['name' => '', 'description' => '', 'price' => '', 'active' => 1], null, 200);
    }

    public static function editProduct(int $id): never
    {
        self::admin();
        $p = self::product($id);
        self::form($id, $p + ['price' => number_format($p['price_cents'] / 100, 2, ',', '.')], null, 200);
    }

    public static function createProduct(): never
    {
        self::save(null);
    }

    public static function updateProduct(int $id): never
    {
        self::save($id);
    }

    /** @return list<array<string,mixed>> */
    private static function images(int $productId): array
    {
        return Db::all('SELECT id, media_id FROM product_images WHERE product_id = ? ORDER BY id', [$productId]);
    }

    /** A capa é sempre a primeira foto. */
    private static function setCover(int $productId): void
    {
        Db::run('UPDATE products SET image_id = (SELECT media_id FROM product_images WHERE product_id = ? ORDER BY id LIMIT 1) WHERE id = ?', [$productId, $productId]);
    }

    public static function image(int $imageId): never
    {
        self::admin();
        $i = Db::one('SELECT media_id FROM product_images WHERE id = ?', [$imageId]);
        if (!$i) {
            App::notFound();
        }
        Media::output((int) $i['media_id'], false);
    }

    /** Exclui uma foto; o produto nunca fica sem nenhuma. */
    public static function deleteImage(int $imageId): never
    {
        $me = self::admin();
        $i = Db::one('SELECT i.product_id, i.media_id FROM product_images i JOIN products p ON p.id = i.product_id WHERE i.id = ? AND p.deleted_at IS NULL', [$imageId]);
        if (!$i) {
            App::notFound();
        }
        $pid = (int) $i['product_id'];
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            // trava o produto: dois cliques simultâneos não apagam as duas últimas fotos
            Db::one('SELECT id FROM products WHERE id = ? FOR UPDATE', [$pid]);
            $left = count(Db::all('SELECT id FROM product_images WHERE product_id = ? FOR UPDATE', [$pid]));
            if ($left <= 1) {
                $pdo->rollBack();
                Http::flash('err', 'O produto precisa de pelo menos uma foto. Envie outra antes de excluir esta.');
                Http::redirect('/admin/loja/' . $pid);
            }
            Db::run('DELETE FROM product_images WHERE id = ?', [$imageId]);
            self::setCover($pid);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Media::delete((int) $i['media_id']);
        Audit::log('product_image_deleted', (int) $me['id'], 'product', $pid);
        Http::flash('ok', 'Foto excluída.');
        Http::redirect('/admin/loja/' . $pid);
    }

    /** Arquivos enviados no campo images[] (vazios ignorados). @return list<array<string,mixed>> */
    private static function uploads(): array
    {
        $f = $_FILES['images'] ?? null;
        if (!is_array($f) || !is_array($f['name'] ?? null)) {
            return [];
        }
        $out = [];
        foreach (array_keys($f['name']) as $i) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $out[] = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            }
        }
        return $out;
    }

    /** Sem nenhum pedido: apaga. Com pedidos: sai da loja e das listas, preservando o histórico de vendas. */
    public static function deleteProduct(int $id): never
    {
        $me = self::admin();
        $p = self::product($id);
        $sold = static fn () => Db::one('SELECT 1 ok FROM order_items i JOIN product_variants v ON v.id = i.variant_id WHERE v.product_id = ? LIMIT 1', [$id]) !== null;
        $soft = $sold();
        if (!$soft) {
            $pdo = Db::pdo();
            $pdo->beginTransaction();
            try {
                $media = array_column(self::images($id), 'media_id');
                Db::run('DELETE FROM product_images WHERE product_id = ?', [$id]);
                Db::run('DELETE FROM product_variants WHERE product_id = ?', [$id]);
                Db::run('DELETE FROM products WHERE id = ?', [$id]);
                $pdo->commit();
                foreach ($media as $mediaId) {
                    Media::delete((int) $mediaId);
                }
            } catch (\PDOException $e) {
                $pdo->rollBack();
                if ($e->getCode() !== '23000') { // um pedido entrou no meio do caminho
                    throw $e;
                }
                $soft = true;
            }
        }
        if ($soft) {
            Db::run('UPDATE products SET deleted_at = NOW(), active = 0 WHERE id = ?', [$id]);
        }
        Audit::log('product_deleted', (int) $me['id'], 'product', $id, ['name' => $p['name'], 'history_kept' => $soft]);
        Http::flash('ok', $soft ? 'Produto excluído da loja. Os pedidos já feitos continuam no histórico e no relatório.' : 'Produto excluído.');
        Http::redirect('/admin/loja');
    }

    private static function save(?int $id): never
    {
        $me = self::admin();
        if ($id) {
            self::product($id); // 404 se não existir
        }
        $old = ['name' => Http::input('name'), 'description' => Http::input('description'), 'price' => Http::input('price'), 'active' => Http::input('active') === '1' ? 1 : 0];
        $fail = static fn (string $msg) => self::form($id, $old, $msg, 422);

        if (!Http::formComplete()) {
            $fail('O envio veio incompleto (arquivo grande demais?). Nada foi salvo.');
        }
        if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 120) {
            $fail('Informe o nome (2 a 120 caracteres).');
        }
        if (mb_strlen($old['description']) > 1000) {
            $fail('A descrição passa de 1000 caracteres.');
        }
        $price = Billing::parseMoney($old['price']);
        if ($price === null) {
            $fail('Preço inválido. Exemplo: 89,90');
        }

        // tamanhos existentes (por id) e novos
        $existing = $id ? array_column(Db::all('SELECT * FROM product_variants WHERE product_id = ?', [$id]), null, 'id') : [];
        $updates = [];
        $posted = is_array($_POST['stock'] ?? null) ? $_POST['stock'] : [];
        foreach ($existing as $vid => $v) {
            $stock = $posted[$vid] ?? null;
            $before = $_POST['stock_before'][$vid] ?? null;
            if (!is_string($stock) || !is_string($before) || !ctype_digit($stock) || !ctype_digit($before) || (int) $stock > 100000) {
                $fail("Estoque inválido no tamanho {$v['size']}.");
            }
            // aplica a diferença digitada: vendas feitas enquanto a tela estava aberta não se perdem
            $updates[$vid] = [(int) $stock - (int) $before, isset($_POST['off'][$vid]) ? 0 : 1];
        }
        $new = [];
        $taken = array_map('mb_strtolower', array_column($existing, 'size'));
        foreach ((is_array($_POST['new'] ?? null) ? $_POST['new'] : []) as $row) {
            $size = is_array($row) && is_string($row['size'] ?? null) ? trim($row['size']) : '';
            $stock = is_array($row) && is_string($row['stock'] ?? null) ? trim($row['stock']) : '';
            if ($size === '' && $stock === '') {
                continue;
            }
            if ($size === '' || mb_strlen($size) > 20 || !ctype_digit($stock === '' ? '0' : $stock) || (int) $stock > 100000) {
                $fail('Confira os tamanhos novos: nome com até 20 caracteres e estoque em número inteiro.');
            }
            if (in_array(mb_strtolower($size), $taken, true)) {
                $fail("O tamanho $size está repetido.");
            }
            $taken[] = mb_strtolower($size);
            $new[] = [$size, (int) $stock];
        }
        if (!$existing && !$new) {
            $fail('Informe pelo menos um tamanho (use "Único" se não houver).');
        }

        $files = self::uploads();
        $have = $id ? count(self::images($id)) : 0;
        if ($have + count($files) < 1) {
            $fail('Envie pelo menos uma foto do produto.');
        }
        if ($have + count($files) > self::MAX_IMAGES) {
            $fail('Cada produto pode ter até ' . self::MAX_IMAGES . ' fotos.');
        }
        $mediaIds = [];
        foreach ($files as $file) {
            $r = Media::store($file, (int) $me['id']);
            if ($r['error'] !== null) {
                array_map([Media::class, 'delete'], $mediaIds);
                $fail($file['name'] . ': ' . $r['error']);
            }
            $mediaIds[] = (int) $r['id'];
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $values = [$old['name'], $old['description'] === '' ? null : $old['description'], $price, $old['active']];
            if ($id) {
                Db::run('UPDATE products SET name = ?, description = ?, price_cents = ?, active = ? WHERE id = ?', [...$values, $id]);
                $pid = $id;
            } else {
                Db::run('INSERT INTO products (name, description, price_cents, active, created_by) VALUES (?,?,?,?,?)', [...$values, $me['id']]);
                $pid = (int) $pdo->lastInsertId();
            }
            foreach ($updates as $vid => [$delta, $active]) {
                Db::run('UPDATE product_variants SET stock = GREATEST(0, CAST(stock AS SIGNED) + ?), active = ? WHERE id = ?', [$delta, $active, $vid]);
            }
            foreach ($new as [$size, $stock]) {
                Db::run('INSERT INTO product_variants (product_id, size, stock) VALUES (?,?,?)', [$pid, $size, $stock]);
            }
            foreach ($mediaIds as $mediaId) {
                Db::run('INSERT INTO product_images (product_id, media_id) VALUES (?,?)', [$pid, $mediaId]);
            }
            self::setCover($pid);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log($id ? 'product_updated' : 'product_created', (int) $me['id'], 'product', $pid, ['price' => $price, 'active' => $old['active']]);
        Http::flash('ok', 'Produto salvo.');
        Http::redirect('/admin/loja/' . $pid);
    }

    // ---- pedidos ----

    public static function orders(): never
    {
        self::admin();
        $filters = [
            'abertos' => 'o.cancelled_at IS NULL AND (o.paid_at IS NULL OR o.delivered_at IS NULL)',
            'concluidos' => 'o.paid_at IS NOT NULL AND o.delivered_at IS NOT NULL AND o.cancelled_at IS NULL',
            'cancelados' => 'o.cancelled_at IS NOT NULL',
            'todos' => '1 = 1',
        ];
        $filter = (string) ($_GET['filtro'] ?? 'abertos');
        $filter = isset($filters[$filter]) ? $filter : 'abertos';
        View::render('shop/admin_orders', ['title' => 'Pedidos', 'shell' => 'admin', 'active' => 'pedidos', 'filter' => $filter,
            'orders' => Db::all("SELECT o.*, u.name AS athlete FROM orders o JOIN users u ON u.id = o.athlete_id WHERE {$filters[$filter]} ORDER BY o.id DESC LIMIT 300")]);
    }

    /** @return array<string,mixed> */
    private static function find(int $id): array
    {
        $o = Db::one('SELECT o.*, u.name AS athlete, u.email FROM orders o JOIN users u ON u.id = o.athlete_id WHERE o.id = ?', [$id]);
        if (!$o) {
            App::notFound();
        }
        return $o;
    }

    public static function order(int $id): never
    {
        self::admin();
        View::render('shop/admin_order', ['title' => "Pedido #$id", 'shell' => 'admin', 'active' => 'pedidos', 'order' => self::find($id), 'items' => Shop::items($id)]);
    }

    public static function orderAction(int $id): never
    {
        $me = self::admin();
        $o = self::find($id);
        $by = (int) $me['id'];
        $action = Http::input('action');
        $ok = false;
        if ($action === 'pay') {
            $ok = Db::run('UPDATE orders SET paid_at = NOW(), paid_by = ? WHERE id = ? AND paid_at IS NULL AND cancelled_at IS NULL', [$by, $id])->rowCount() === 1;
        } elseif ($action === 'unpay') {
            $ok = Db::run('UPDATE orders SET paid_at = NULL, paid_by = NULL WHERE id = ? AND paid_at IS NOT NULL AND cancelled_at IS NULL', [$id])->rowCount() === 1;
        } elseif ($action === 'deliver') {
            $ok = Db::run('UPDATE orders SET delivered_at = NOW() WHERE id = ? AND delivered_at IS NULL AND cancelled_at IS NULL', [$id])->rowCount() === 1;
        } elseif ($action === 'cancel') {
            $ok = Shop::cancel($id, $by);
        } elseif ($action === 'link') {
            $link = Http::input('payment_link');
            if ($link === '' || self::validUrl($link)) {
                Db::run('UPDATE orders SET payment_link = ? WHERE id = ?', [$link === '' ? null : $link, $id]);
                $ok = true;
            }
        }
        if ($ok && $action !== 'cancel') {
            Audit::log('order_' . $action, $by, 'order', $id, ['athlete' => (int) $o['athlete_id']]);
        }
        Http::flash($ok ? 'ok' : 'err', $ok ? 'Pedido atualizado.' : 'Ação não permitida para a situação deste pedido (o link precisa começar com https://).');
        Http::redirect('/admin/pedidos/' . $id);
    }

    // ---- relatório de vendas ----

    public static function sales(): never
    {
        self::admin();
        $date = static function (string $key, string $default): string {
            $v = (string) ($_GET[$key] ?? '');
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            return $d && $d->format('Y-m-d') === $v ? $v : $default;
        };
        $from = $date('de', date('Y-m-01'));
        $to = $date('ate', date('Y-m-d'));
        $range = [$from . ' 00:00:00', $to . ' 23:59:59'];
        View::render('shop/admin_sales', ['title' => 'Vendas', 'shell' => 'admin', 'active' => 'vendas', 'from' => $from, 'to' => $to,
            'sold' => Db::one('SELECT COUNT(*) n, COALESCE(SUM(total_cents), 0) total FROM orders WHERE cancelled_at IS NULL AND created_at BETWEEN ? AND ?', $range),
            'received' => Db::one('SELECT COUNT(*) n, COALESCE(SUM(total_cents), 0) total FROM orders WHERE cancelled_at IS NULL AND paid_at BETWEEN ? AND ?', $range),
            'pending' => Db::one('SELECT COUNT(*) n, COALESCE(SUM(total_cents), 0) total FROM orders WHERE cancelled_at IS NULL AND paid_at IS NULL'),
            'cancelled' => (int) Db::one('SELECT COUNT(*) c FROM orders WHERE cancelled_at BETWEEN ? AND ?', $range)['c'],
            'byItem' => Db::all(
                'SELECT i.product_name, i.size, SUM(i.qty) qty, SUM(i.qty * i.unit_cents) total FROM order_items i JOIN orders o ON o.id = i.order_id
                 WHERE o.cancelled_at IS NULL AND o.created_at BETWEEN ? AND ? GROUP BY i.product_name, i.size ORDER BY qty DESC, i.product_name', $range),
            'byMethod' => Db::all('SELECT payment_method, COUNT(*) n, SUM(total_cents) total FROM orders WHERE cancelled_at IS NULL AND created_at BETWEEN ? AND ? GROUP BY payment_method', $range),
            'stock' => Db::all('SELECT p.name, v.size, v.stock FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.active = 1 AND p.active = 1 ORDER BY p.name, v.id')]);
    }
}
