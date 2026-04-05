<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;

class MailgunWebhookHandler extends AbstractWebhookHandler
{
    protected function provider(): TrackingProvider
    {
        return TrackingProvider::Mailgun;
    }

    public function validate(Request $request): bool
    {
        $signingKey = config('laravel-mail.tracking.providers.mailgun.signing_key');

        if (! $signingKey) {
            return true;
        }

        $signature = $request->input('signature', []);
        $timestamp = $signature['timestamp'] ?? '';
        $token = $signature['token'] ?? '';
        $providedSignature = $signature['signature'] ?? '';

        if (! $timestamp || ! $token || ! $providedSignature) {
            return false;
        }

        // Verify timestamp is recent (within 5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.$token, $signingKey);

        return hash_equals($expectedSignature, $providedSignature);
    }

    public function handle(Request $request): void
    {
        $payload = $request->all();
        $eventData = $payload['event-data'] ?? $payload;
        $event = $eventData['event'] ?? null;
        $messageHeaders = $eventData['message']['headers'] ?? [];
        $messageId = $messageHeaders['message-id'] ?? $eventData['message-id'] ?? null;

        if (! $messageId) {
            return;
        }

        $mailLog = $this->findMailLog($messageId) ?? $this->findMailLog("<{$messageId}>");

        if (! $mailLog) {
            return;
        }

        $eventType = $this->mapEventType($event);

        if (! $eventType) {
            return;
        }

        $occurredAt = isset($eventData['timestamp'])
            ? Carbon::createFromTimestamp((float) $eventData['timestamp'])
            : null;

        $bounceType = null;
        if ($event === 'failed') {
            $severity = $eventData['severity'] ?? 'permanent';
            $reason = $eventData['reason'] ?? 'unknown';
            $bounceType = "{$severity}/{$reason}";
        }

        $this->recordEvent(
            mailLog: $mailLog,
            type: $eventType,
            payload: $eventData,
            recipient: $eventData['recipient'] ?? null,
            url: $eventData['url'] ?? null,
            bounceType: $bounceType,
            occurredAt: $occurredAt,
        );
    }

    protected function mapEventType(?string $event): ?TrackingEventType
    {
        return match ($event) {
            'delivered' => TrackingEventType::Delivered,
            'failed', 'rejected' => TrackingEventType::Bounced,
            'complained' => TrackingEventType::Complained,
            'opened' => TrackingEventType::Opened,
            'clicked' => TrackingEventType::Clicked,
            default => null,
        };
    }
}
