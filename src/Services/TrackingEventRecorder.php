<?php

namespace JeffersonGoncalves\LaravelMail\Services;

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

class TrackingEventRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        MailLog $mailLog,
        TrackingEventType $type,
        TrackingProvider $provider,
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
                'provider' => $provider,
                'occurred_at' => $occurredAt,
            ];

        $event = $modelClass::firstOrCreate($uniqueAttributes, [
            'mail_log_id' => $mailLog->id,
            'type' => $type,
            'provider' => $provider,
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

    public function dispatchTrackingEvent(MailLog $mailLog, MailTrackingEvent $trackingEvent, TrackingEventType $type): void
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

    public function updateMailLogStatus(MailLog $mailLog, TrackingEventType $eventType): void
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
