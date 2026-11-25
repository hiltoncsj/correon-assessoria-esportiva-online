<?php
declare(strict_types=1);
namespace Correon;

/** Upload de fotos: valida, REGRAVA a imagem (remove metadados como GPS), reduz e guarda fora da pasta pública. */
final class Media
{
    private const MAX_BYTES = 4_194_304;   // 4 MB
    private const MAX_PIXELS = 24_000_000;
    private const MAX_WIDTH = 1280;

    public static function dir(): string
    {
        $d = BASE_PATH . '/storage/uploads';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    /**
     * @param array<string,mixed> $file item de $_FILES
     * @return array{id:int|null,error:string|null}
     */
    public static function store(array $file, int $userId): array
    {
        $fail = static fn (string $m) => ['id' => null, 'error' => $m];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return $fail('Falha no envio da foto (tamanho máximo: 4 MB).');
        }
        $tmp = (string) $file['tmp_name'];
        if (filesize($tmp) > self::MAX_BYTES) {
            return $fail('A foto passa de 4 MB.');
        }
        $info = @getimagesize($tmp);
        $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
        if (!$info || !isset($loaders[$info[2]]) || !function_exists($loaders[$info[2]])) {
            return $fail('Envie uma foto JPG, PNG ou WebP.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            return $fail('A foto tem resolução grande demais (máx. 24 megapixels).');
        }
        $img = @$loaders[$info[2]]($tmp);
        if (!$img) {
            return $fail('Não foi possível ler a foto.');
        }
        // fotos de celular guardam a rotação nos metadados; aplicamos antes de descartá-los
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($angle !== 0) {
                $img = imagerotate($img, $angle, 0) ?: $img;
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $nw = min($w, self::MAX_WIDTH);
        $nh = (int) max(1, round($h * $nw / $w));
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $webp = function_exists('imagewebp');
        $name = bin2hex(random_bytes(16)) . ($webp ? '.webp' : '.jpg');
        $path = self::dir() . '/' . $name;
        $ok = $webp ? imagewebp($out, $path, 82) : imagejpeg($out, $path, 84);
        imagedestroy($img);
        imagedestroy($out);
        if (!$ok) {
            return $fail('Não foi possível salvar a foto.');
        }
        Db::run('INSERT INTO media (filename, mime, width, height, uploaded_by) VALUES (?,?,?,?,?)', [$name, $webp ? 'image/webp' : 'image/jpeg', $nw, $nh, $userId]);
        return ['id' => (int) Db::pdo()->lastInsertId(), 'error' => null];
    }

    public static function output(int $id, bool $public): never
    {
        $m = Db::one('SELECT filename, mime FROM media WHERE id = ?', [$id]);
        $path = $m ? self::dir() . '/' . basename($m['filename']) : '';
        if (!$m || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $m['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($path));
        // público: cache curto, para a retirada de consentimento valer rápido
        header('Cache-Control: ' . ($public ? 'public, max-age=300' : 'private, no-store'));
        readfile($path);
        exit;
    }
}
