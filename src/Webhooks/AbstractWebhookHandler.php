<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use JeffersonGoncalves\LaravelMail\Contracts\WebhookHandler;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

abstract class AbstractWebhookHandler implements WebhookHandler
{
    abstract protected function provider(): TrackingProvider;

    protected function findMailLog(string $providerMessageId): ?MailLog
    {
        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        return $modelClass::where('provider_message_id', $providerMessageId)->first();
    }

    protected function recordEvent(
        MailLog $mailLog,
        TrackingEventType $type,
        array $payload = [],
        ?string $recipient = null,
        ?string $url = null,
        ?string $bounceType = null,
        ?\DateTimeInterface $occurredAt = null,
    ): MailTrackingEvent {
        $modelClass = config('laravel-mail.models.mail_tracking_event', MailTrackingEvent::class);

        $event = $modelClass::create([
            'mail_log_id' => $mailLog->id,
            'type' => $type,
            'provider' => $this->provider(),
            'payload' => $payload,
            'recipient' => $recipient,
            'url' => $url,
            'bounce_type' => $bounceType,
            'occurred_at' => $occurredAt,
            'created_at' => now(),
        ]);

        $this->updateMailLogStatus($mailLog, $type);

        return $event;
    }

    protected function updateMailLogStatus(MailLog $mailLog, TrackingEventType $eventType): void
    {
        $statusMap = [
            TrackingEventType::Delivered->value => MailStatus::Delivered,
            TrackingEventType::Bounced->value => MailStatus::Bounced,
            TrackingEventType::Complained->value => MailStatus::Complained,
        ];

        $newStatus = $statusMap[$eventType->value] ?? null;

        if ($newStatus) {
            $mailLog->update(['status' => $newStatus]);
        }
    }
}
