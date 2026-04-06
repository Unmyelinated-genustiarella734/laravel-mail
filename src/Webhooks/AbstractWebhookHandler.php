<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use JeffersonGoncalves\LaravelMail\Contracts\WebhookHandler;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Events\MailBounced;
use JeffersonGoncalves\LaravelMail\Events\MailClicked;
use JeffersonGoncalves\LaravelMail\Events\MailComplained;
use JeffersonGoncalves\LaravelMail\Events\MailDeferred;
use JeffersonGoncalves\LaravelMail\Events\MailDelivered;
use JeffersonGoncalves\LaravelMail\Events\MailOpened;
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
        $modelClass = config('laravel-mail.models.mail_tracking_event', MailTrackingEvent::class);

        $uniqueAttributes = $providerEventId
            ? ['provider_event_id' => $providerEventId]
            : [
                'mail_log_id' => $mailLog->id,
                'type' => $type,
                'provider' => $this->provider(),
                'occurred_at' => $occurredAt,
            ];

        $event = $modelClass::firstOrCreate($uniqueAttributes, [
            'mail_log_id' => $mailLog->id,
            'type' => $type,
            'provider' => $this->provider(),
            'provider_event_id' => $providerEventId,
            'payload' => $payload,
            'recipient' => $recipient,
            'url' => $url,
            'bounce_type' => $bounceType,
            'occurred_at' => $occurredAt,
            'created_at' => now(),
        ]);

        if (! $event->wasRecentlyCreated) {
            return $event;
        }

        $this->updateMailLogStatus($mailLog, $type);
        $this->dispatchTrackingEvent($mailLog, $event, $type);

        return $event;
    }

    protected function dispatchTrackingEvent(MailLog $mailLog, MailTrackingEvent $trackingEvent, TrackingEventType $type): void
    {
        $eventClass = match ($type) {
            TrackingEventType::Delivered => MailDelivered::class,
            TrackingEventType::Bounced => MailBounced::class,
            TrackingEventType::Complained => MailComplained::class,
            TrackingEventType::Opened => MailOpened::class,
            TrackingEventType::Clicked => MailClicked::class,
            TrackingEventType::Deferred => MailDeferred::class,
        };

        event(new $eventClass($mailLog, $trackingEvent));
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
