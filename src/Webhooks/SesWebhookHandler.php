<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;

class SesWebhookHandler extends AbstractWebhookHandler
{
    protected function provider(): TrackingProvider
    {
        return TrackingProvider::Ses;
    }

    public function validate(Request $request): bool
    {
        // SES sends SNS notifications. Validation is done via the message signature.
        // For SNS, we verify the SigningCertURL is from amazonaws.com and validate the signature.
        $payload = $request->all();

        if (! isset($payload['Type'])) {
            return false;
        }

        // Verify the certificate URL is from AWS
        if (isset($payload['SigningCertURL'])) {
            $parsed = parse_url($payload['SigningCertURL']);
            if (! isset($parsed['host']) || ! str_ends_with($parsed['host'], '.amazonaws.com')) {
                return false;
            }
        }

        return true;
    }

    public function handle(Request $request): void
    {
        $payload = $request->all();

        // Handle SNS subscription confirmation
        if (($payload['Type'] ?? null) === 'SubscriptionConfirmation') {
            if (isset($payload['SubscribeURL'])) {
                rescue(fn () => Http::get($payload['SubscribeURL']));
            }

            return;
        }

        if (($payload['Type'] ?? null) !== 'Notification') {
            return;
        }

        $message = json_decode($payload['Message'] ?? '{}', true);
        $notificationType = $message['notificationType'] ?? $message['eventType'] ?? null;
        $mail = $message['mail'] ?? [];
        $messageId = $mail['messageId'] ?? null;

        if (! $messageId) {
            return;
        }

        // SES messageId is wrapped in angle brackets in the provider_message_id
        $mailLog = $this->findMailLog($messageId) ?? $this->findMailLog("<{$messageId}>");

        if (! $mailLog) {
            return;
        }

        $eventType = $this->mapEventType($notificationType);

        if (! $eventType) {
            return;
        }

        $recipient = $this->extractRecipient($message, $notificationType);
        $bounceType = null;

        if ($notificationType === 'Bounce') {
            $bounce = $message['bounce'] ?? [];
            $bounceType = ($bounce['bounceType'] ?? 'Undetermined').'/'.($bounce['bounceSubType'] ?? 'Undetermined');
        }

        $occurredAt = isset($mail['timestamp'])
            ? Carbon::parse($mail['timestamp'])
            : null;

        $this->recordEvent(
            mailLog: $mailLog,
            type: $eventType,
            payload: $message,
            recipient: $recipient,
            bounceType: $bounceType,
            occurredAt: $occurredAt,
            providerEventId: $payload['MessageId'] ?? null,
        );
    }

    protected function mapEventType(?string $type): ?TrackingEventType
    {
        return match ($type) {
            'Delivery' => TrackingEventType::Delivered,
            'Bounce' => TrackingEventType::Bounced,
            'Complaint' => TrackingEventType::Complained,
            'Open' => TrackingEventType::Opened,
            'Click' => TrackingEventType::Clicked,
            'DeliveryDelay' => TrackingEventType::Deferred,
            default => null,
        };
    }

    protected function extractRecipient(array $message, ?string $type): ?string
    {
        return match ($type) {
            'Delivery' => $message['delivery']['recipients'][0] ?? null,
            'Bounce' => $message['bounce']['bouncedRecipients'][0]['emailAddress'] ?? null,
            'Complaint' => $message['complaint']['complainedRecipients'][0]['emailAddress'] ?? null,
            'Open' => $message['open']['recipients'][0] ?? null,
            'Click' => $message['click']['recipients'][0] ?? null,
            default => null,
        };
    }
}
