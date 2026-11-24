<?php
declare(strict_types=1);
namespace Correon;

final class Mailer
{
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
