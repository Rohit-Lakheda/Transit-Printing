<?php

namespace App\Services;

use App\Models\MailConfiguration;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ConfiguredMailerService
{
    /**
     * Symfony EsmtpTransport TLS flag:
     * - true  = implicit SSL/SMTPS (typically port 465)
     * - false = plain TCP, then STARTTLS upgrade (typically port 587)
     * - null  = no encryption
     */
    public static function createTransport(MailConfiguration $config): EsmtpTransport
    {
        $transport = new EsmtpTransport(
            $config->host,
            $config->port,
            match ($config->encryption) {
                'ssl' => true,
                'tls' => false,
                default => null,
            }
        );

        if ($config->use_auth && $config->username) {
            $transport->setUsername($config->username);
            if ($config->password) {
                $transport->setPassword($config->password);
            }
        }

        return $transport;
    }

    public function sendHtml(
        string $toEmail,
        string $subject,
        string $htmlBody,
        MailConfiguration $config,
        array $attachments = []
    ): void {
        $mailer = new Mailer(self::createTransport($config));
        $fromAddress = $config->from_address ?: config('mail.from.address');
        $fromName = $config->from_name ?: config('mail.from.name');

        $message = (new Email())
            ->from(new Address($fromAddress, (string) $fromName))
            ->to($toEmail)
            ->subject($subject)
            ->html($htmlBody);

        foreach ($attachments as $attachment) {
            $content = $attachment['content'] ?? null;
            $filename = $attachment['filename'] ?? 'attachment.pdf';
            $mime = $attachment['mime'] ?? 'application/octet-stream';

            if ($content === null) {
                continue;
            }

            $message->attach($content, $filename, $mime);
        }

        $mailer->send($message);
    }
}
