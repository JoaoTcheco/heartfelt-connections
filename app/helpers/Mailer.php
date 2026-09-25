<?php
/**
 * ============================================================
 * FarmaPonto - Mailer Helper
 * ============================================================
 * Tenta enviar via PHPMailer; em caso de ausência, escreve o
 * email em storage/mail.log para inspecção em ambientes de
 * desenvolvimento ou hospedagem partilhada sem SMTP.
 */

namespace App\Helpers;

final class Mailer {

    public static function send(string $to, string $subject, string $html): bool {
        $cfg = require __DIR__ . '/../../config/config.php';

        // Fallback: regista no log se PHPMailer não estiver disponível
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            self::logFallback($to, $subject, $html);
            return false;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $cfg['smtp_host'] ?? '';
            $mail->Port       = (int) ($cfg['smtp_port'] ?? 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['smtp_user'] ?? '';
            $mail->Password   = $cfg['smtp_pass'] ?? '';
            $mail->SMTPSecure = ($cfg['smtp_secure'] ?? 'tls') === 'tls'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($cfg['smtp_from_email'] ?? 'no-reply@farmaponto.local', $cfg['smtp_from_name'] ?? 'FarmaPonto');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags($html);

            return $mail->send();
        } catch (\Throwable $e) {
            error_log("[FarmaPonto] Email fail: " . $e->getMessage());
            self::logFallback($to, $subject, $html);
            return false;
        }
    }

    private static function logFallback(string $to, string $subject, string $html): void {
        $dir = __DIR__ . '/../../storage';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $line = "==== " . date('Y-m-d H:i:s') . " ====\nTo: $to\nSubject: $subject\n\n$html\n\n";
        @file_put_contents($dir . '/mail.log', $line, FILE_APPEND);
    }
}
