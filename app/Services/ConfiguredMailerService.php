<?php

namespace App\Services;

use App\Models\MailConfiguration;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ConfiguredMailerService
{
    public function sendHtml(
        string $toEmail,
        string $subject,
        string $htmlBody,
        MailConfiguration $config,
        array $attachments = []
    ): void {
        $transport = new EsmtpTransport(
            $config->host,
            $config->port,
            $config->encryption === 'ssl' || $config->encryption === 'tls'
        );

        if ($config->use_auth && $config->username) {
            $transport->setUsername($config->username);
            if ($config->password) {
                $transport->setPassword($config->password);
            }
        }

        $mailer = new Mailer($transport);
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
