<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;

class ResendWebhookHandler extends AbstractWebhookHandler
{
    protected function provider(): TrackingProvider
    {
        return TrackingProvider::Resend;
    }

    public function validate(Request $request): bool
    {
        $signingSecret = config('laravel-mail.tracking.providers.resend.signing_secret');

        if (! $signingSecret) {
            return true;
        }

        $signature = $request->header('svix-signature');
        $messageId = $request->header('svix-id');
        $timestamp = $request->header('svix-timestamp');

        if (! $signature || ! $messageId || ! $timestamp) {
            return false;
        }

        // Verify timestamp is recent (within 5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        // Resend uses Svix for webhooks (HMAC SHA256)
        $body = $request->getContent();
        $toSign = "{$messageId}.{$timestamp}.{$body}";

        // The secret is base64-encoded, prefixed with "whsec_"
        $secret = $signingSecret;
        if (str_starts_with($secret, 'whsec_')) {
            $secret = substr($secret, 6);
        }
        $secretBytes = base64_decode($secret);

        $expectedSignature = base64_encode(hash_hmac('sha256', $toSign, $secretBytes, true));

        // Svix sends multiple signatures separated by spaces, each prefixed with "v1,"
        $signatures = explode(' ', $signature);
        foreach ($signatures as $sig) {
            $parts = explode(',', $sig, 2);
            if (count($parts) === 2 && $parts[0] === 'v1' && hash_equals($expectedSignature, $parts[1])) {
                return true;
            }
        }

        return false;
    }

    public function handle(Request $request): void
    {
        $payload = $request->all();
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];
        $emailId = $data['email_id'] ?? null;

        if (! $emailId) {
            return;
        }

        $mailLog = $this->findMailLog($emailId) ?? $this->findMailLog("<{$emailId}>");

        if (! $mailLog) {
            return;
        }

        $eventType = $this->mapEventType($type);

        if (! $eventType) {
            return;
        }

        $occurredAt = isset($data['created_at'])
            ? Carbon::parse($data['created_at'])
            : null;

        $bounceType = null;
        if ($type === 'email.bounced') {
            $bounceType = $data['bounce_type'] ?? 'unknown';
        }

        $this->recordEvent(
            mailLog: $mailLog,
            type: $eventType,
            payload: $data,
            recipient: $data['to'][0] ?? null,
            url: $data['click']['link'] ?? null,
            bounceType: $bounceType,
            occurredAt: $occurredAt,
        );
    }

    protected function mapEventType(?string $type): ?TrackingEventType
    {
        return match ($type) {
            'email.delivered' => TrackingEventType::Delivered,
            'email.bounced' => TrackingEventType::Bounced,
            'email.complained' => TrackingEventType::Complained,
            'email.opened' => TrackingEventType::Opened,
            'email.clicked' => TrackingEventType::Clicked,
            'email.delivery_delayed' => TrackingEventType::Deferred,
            default => null,
        };
    }
}
