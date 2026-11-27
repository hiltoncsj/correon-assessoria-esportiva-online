<?php
declare(strict_types=1);
namespace Correon;

final class Mailer
{
    /** E-mail com versão texto, versão HTML e o logo embutido (src="cid:logo-correon"). */
    public static function sendHtml(string $to, string $subject, string $text, string $html, string $logoPath): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? '');
        if (Env::get('MAIL_DRIVER', 'mail') === 'log') {
            self::send($to, $subject, $text);
            $dir = BASE_PATH . '/storage/mail';
            $logo = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath)) : '';
            file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.html', str_replace('cid:logo-correon', $logo, $html));
            return true;
        }
        $related = 'rel_' . bin2hex(random_bytes(12));
        $alt = 'alt_' . bin2hex(random_bytes(12));
        $part = static fn (string $type, string $content) => "Content-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($content));
        $body = "--$related\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n"
            . "--$alt\r\n" . $part('text/plain', $text)
            . "--$alt\r\n" . $part('text/html', $html)
            . "--$alt--\r\n";
        if (is_file($logoPath)) {
            $body .= "--$related\r\nContent-Type: image/png; name=\"correon.png\"\r\nContent-Transfer-Encoding: base64\r\n"
                . "Content-ID: <logo-correon>\r\nContent-Disposition: inline; filename=\"correon.png\"\r\n\r\n"
                . chunk_split(base64_encode((string) file_get_contents($logoPath)));
        }
        $body .= "--$related--\r\n";
        $headers = "MIME-Version: 1.0\r\nContent-Type: multipart/related; boundary=\"$related\"\r\n"
            . 'From: ' . mb_encode_mimeheader(Env::get('MAIL_FROM_NAME', 'CorreON')) . ' <' . Env::get('MAIL_FROM', '') . ">\r\n";
        return @mail($to, mb_encode_mimeheader($subject), $body, $headers);
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        if (Env::get('MAIL_DRIVER', 'mail') === 'log') {
            $dir = BASE_PATH . '/storage/mail';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents(
                $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt',
                "Para: $to\nAssunto: $subject\n\n$body\n"
            );
            return true;
        }
        $from = Env::get('MAIL_FROM', '');
        $name = Env::get('MAIL_FROM_NAME', 'CorreON');
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n"
            . 'From: ' . mb_encode_mimeheader($name) . " <$from>\r\n";
        return @mail($to, mb_encode_mimeheader($subject), $body, $headers);
    }
}
