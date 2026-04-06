<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;

class PostmarkWebhookHandler extends AbstractWebhookHandler
{
    protected function provider(): TrackingProvider
    {
        return TrackingProvider::Postmark;
    }

    public function validate(Request $request): bool
    {
        $username = config('laravel-mail.tracking.providers.postmark.username');
        $password = config('laravel-mail.tracking.providers.postmark.password');

        if (! $username && ! $password) {
            return true;
        }

        // Postmark uses HTTP Basic Auth for webhook validation
        $providedUser = $request->getUser();
        $providedPass = $request->getPassword();

        return $providedUser === $username && $providedPass === $password;
    }

    public function handle(Request $request): void
    {
        $payload = $request->all();
        $recordType = $payload['RecordType'] ?? null;
        $messageId = $payload['MessageID'] ?? null;

        if (! $messageId) {
            return;
        }

        $mailLog = $this->findMailLog($messageId) ?? $this->findMailLog("<{$messageId}@>");

        if (! $mailLog) {
            return;
        }

        $eventType = $this->mapEventType($recordType);

        if (! $eventType) {
            return;
        }

        $occurredAt = $this->extractTimestamp($payload, $recordType);
        $bounceType = null;

        if ($recordType === 'Bounce') {
            $bounceType = ($payload['Type'] ?? 'Unknown').'/'.($payload['TypeCode'] ?? '0');
        }

        $this->recordEvent(
            mailLog: $mailLog,
            type: $eventType,
            payload: $payload,
            recipient: $payload['Recipient'] ?? $payload['Email'] ?? null,
            url: $payload['OriginalLink'] ?? null,
            bounceType: $bounceType,
            occurredAt: $occurredAt,
            providerEventId: "{$messageId}-{$recordType}",
        );
    }

    protected function mapEventType(?string $recordType): ?TrackingEventType
    {
        return match ($recordType) {
            'Delivery' => TrackingEventType::Delivered,
            'Bounce' => TrackingEventType::Bounced,
            'SpamComplaint' => TrackingEventType::Complained,
            'Open' => TrackingEventType::Opened,
            'Click' => TrackingEventType::Clicked,
            default => null,
        };
    }

    protected function extractTimestamp(array $payload, ?string $recordType): ?Carbon
    {
        $field = match ($recordType) {
            'Delivery' => 'DeliveredAt',
            'Bounce' => 'BouncedAt',
            'Open' => 'ReceivedAt',
            'Click' => 'ReceivedAt',
            'SpamComplaint' => 'BouncedAt',
            default => null,
        };

        if ($field && isset($payload[$field])) {
            return Carbon::parse($payload[$field]);
        }

        return null;
    }
}
