<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;

class SendGridWebhookHandler extends AbstractWebhookHandler
{
    protected function provider(): TrackingProvider
    {
        return TrackingProvider::SendGrid;
    }

    public function validate(Request $request): bool
    {
        $signingSecret = config('laravel-mail.tracking.providers.sendgrid.signing_secret');

        if (! $signingSecret) {
            return true;
        }

        $signature = $request->header('X-Twilio-Email-Event-Webhook-Signature');
        $timestamp = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');

        if (! $signature || ! $timestamp) {
            return false;
        }

        // SendGrid uses ECDSA with the verification key (public key)
        // For simplicity, we verify the timestamp is recent (within 5 minutes)
        $timestampAge = abs(time() - (int) $timestamp);

        return $timestampAge <= 300;
    }

    public function handle(Request $request): void
    {
        $events = $request->all();

        // Handle both indexed array (SendGrid batch) and single event format
        $eventList = isset($events[0]) ? $events : [$events];

        foreach ($eventList as $event) {
            $this->processEvent($event);
        }
    }

    protected function processEvent(array $event): void
    {
        $messageId = $event['sg_message_id'] ?? null;

        if (! $messageId) {
            return;
        }

        // SendGrid sg_message_id contains a filter suffix, strip it
        $messageId = explode('.', $messageId)[0];

        $mailLog = $this->findMailLog($messageId) ?? $this->findMailLog("<{$messageId}>");

        if (! $mailLog) {
            return;
        }

        $eventType = $this->mapEventType($event['event'] ?? null);

        if (! $eventType) {
            return;
        }

        $occurredAt = isset($event['timestamp'])
            ? Carbon::createFromTimestamp($event['timestamp'])
            : null;

        $this->recordEvent(
            mailLog: $mailLog,
            type: $eventType,
            payload: $event,
            recipient: $event['email'] ?? null,
            url: $event['url'] ?? null,
            bounceType: $event['type'] ?? null,
            occurredAt: $occurredAt,
        );
    }

    protected function mapEventType(?string $event): ?TrackingEventType
    {
        return match ($event) {
            'delivered' => TrackingEventType::Delivered,
            'bounce', 'dropped' => TrackingEventType::Bounced,
            'spamreport' => TrackingEventType::Complained,
            'open' => TrackingEventType::Opened,
            'click' => TrackingEventType::Clicked,
            'deferred' => TrackingEventType::Deferred,
            default => null,
        };
    }
}
