<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use JeffersonGoncalves\LaravelMail\Contracts\WebhookHandler;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;
use JeffersonGoncalves\LaravelMail\Services\TrackingEventRecorder;

abstract class AbstractWebhookHandler implements WebhookHandler
{
    abstract protected function provider(): TrackingProvider;

    protected function findMailLog(string $providerMessageId): ?MailLog
    {
        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        return $modelClass::where('provider_message_id', $providerMessageId)->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordEvent(
        MailLog $mailLog,
        TrackingEventType $type,
        array $payload = [],
        ?string $recipient = null,
        ?string $url = null,
        ?string $bounceType = null,
        ?\DateTimeInterface $occurredAt = null,
        ?string $providerEventId = null,
    ): MailTrackingEvent {
        return (new TrackingEventRecorder)->record(
            mailLog: $mailLog,
            type: $type,
            provider: $this->provider(),
            payload: $payload,
            recipient: $recipient,
            url: $url,
            bounceType: $bounceType,
            occurredAt: $occurredAt,
            providerEventId: $providerEventId,
        );
    }
}
