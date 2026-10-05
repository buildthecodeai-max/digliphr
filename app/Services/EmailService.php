<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    public function send(string $to, string $subject, string $htmlBody, ?string $toName = null): bool
    {
        $mailConfig = config('mail');
        $logger = new Logger();

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) ($mailConfig['host'] ?? 'localhost');
            $mail->Port = (int) ($mailConfig['port'] ?? 587);
            $mail->SMTPAuth = !empty($mailConfig['username']);
            if ($mail->SMTPAuth) {
                $mail->Username = (string) $mailConfig['username'];
                $mail->Password = (string) $mailConfig['password'];
            }
            $encryption = $mailConfig['encryption'] ?? 'tls';
            if ($encryption) {
                $mail->SMTPSecure = $encryption;
            }

            $mail->setFrom(
                (string) ($mailConfig['from']['address'] ?? 'noreply@example.com'),
                (string) ($mailConfig['from']['name'] ?? 'EMS')
            );
            $mail->addAddress($to, $toName ?? '');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            $logger->error('Email send failed: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
            return false;
        }
    }
}
