<?php

namespace JeffersonGoncalves\LaravelMail\Listeners;

use Illuminate\Mail\Events\MessageSent;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class LogSentMessage
{
    public function handle(MessageSent $event): void
    {
        $sentMessage = $event->sent;
        $message = $event->message;

        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        $id = $this->extractHeaderValue($message, 'X-LaravelMail-ID');

        $data = [
            'mailer' => $event->data['__laravel_notification_mailer'] ?? config('mail.default'),
            'subject' => $message->getSubject(),
            'from' => $this->formatAddresses($message->getFrom()),
            'to' => $this->formatAddresses($message->getTo()),
            'cc' => $this->formatAddresses($message->getCc()),
            'bcc' => $this->formatAddresses($message->getBcc()),
            'reply_to' => $this->formatAddresses($message->getReplyTo()),
            'headers' => $this->extractHeaders($message),
            'status' => MailStatus::Sent,
        ];

        if (config('laravel-mail.logging.store_html_body', true)) {
            $data['html_body'] = $message->getHtmlBody();
        }

        if (config('laravel-mail.logging.store_text_body', true)) {
            $data['text_body'] = $message->getTextBody();
        }

        if (config('laravel-mail.logging.store_attachments', true)) {
            $data['attachments'] = $this->extractAttachments($message);
        }

        $providerMessageId = $sentMessage->getMessageId();
        if ($providerMessageId) {
            $data['provider_message_id'] = $providerMessageId;
        }

        $metadata = $event->data;
        unset($metadata['__laravel_notification_mailer'], $metadata['__laravel_notification']);

        if (! empty($metadata)) {
            $data['metadata'] = $metadata;
        }

        if (config('laravel-mail.tenant.enabled', false)) {
            $tenantId = $event->data['__tenant_id'] ?? null;
            if ($tenantId) {
                $data[config('laravel-mail.tenant.column', 'tenant_id')] = $tenantId;
            }
        }

        if ($id) {
            $data['id'] = $id;
        }

        $modelClass::create($data);
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{email: string, name: string}>|null
     */
    protected function formatAddresses(array $addresses): ?array
    {
        if (empty($addresses)) {
            return null;
        }

        return array_map(fn (Address $address) => [
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], $addresses);
    }

    /**
     * @return array<string, string>
     */
    protected function extractHeaders(Email $message): array
    {
        $headers = [];

        foreach ($message->getHeaders()->all() as $header) {
            $name = $header->getName();

            if (in_array(strtolower($name), ['to', 'from', 'cc', 'bcc', 'reply-to', 'subject', 'mime-version', 'content-type'])) {
                continue;
            }

            $headers[$name] = $header->getBodyAsString();
        }

        return $headers;
    }

    /**
     * @return array<int, array{filename: string|null, content_type: string|null, size: int|null}>|null
     */
    protected function extractAttachments(Email $message): ?array
    {
        $attachments = $message->getAttachments();

        if (empty($attachments)) {
            return null;
        }

        return array_map(function ($attachment) {
            $headers = $attachment->getPreparedHeaders();

            return [
                'filename' => $headers->getHeaderParameter('content-disposition', 'filename'),
                'content_type' => $headers->get('content-type')?->getBodyAsString(),
                'size' => strlen($attachment->getBody()),
            ];
        }, $attachments);
    }

    protected function extractHeaderValue(Email $message, string $name): ?string
    {
        $header = $message->getHeaders()->get($name);

        return $header?->getBodyAsString();
    }
}
