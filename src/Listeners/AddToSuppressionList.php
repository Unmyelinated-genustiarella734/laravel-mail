<?php

namespace JeffersonGoncalves\LaravelMail\Listeners;

use JeffersonGoncalves\LaravelMail\Enums\SuppressionReason;
use JeffersonGoncalves\LaravelMail\Events\MailBounced;
use JeffersonGoncalves\LaravelMail\Events\MailComplained;
use JeffersonGoncalves\LaravelMail\Models\MailSuppression;

class AddToSuppressionList
{
    public function handle(MailBounced|MailComplained $event): void
    {
        if ($event instanceof MailBounced) {
            if (! config('laravel-mail.suppression.auto_suppress_hard_bounces', true)) {
                return;
            }

            $bounceType = $event->trackingEvent->bounce_type ?? '';
            if (! str_starts_with($bounceType, 'Permanent') && ! str_starts_with($bounceType, 'permanent') && $bounceType !== 'hard') {
                return;
            }

            $reason = SuppressionReason::HardBounce;
        } else {
            if (! config('laravel-mail.suppression.auto_suppress_complaints', true)) {
                return;
            }

            $reason = SuppressionReason::Complaint;
        }

        $recipient = $event->trackingEvent->recipient;

        if (! $recipient) {
            return;
        }

        $modelClass = config('laravel-mail.models.mail_suppression', MailSuppression::class);

        $attributes = ['email' => $recipient];

        if (config('laravel-mail.tenant.enabled', false)) {
            $attributes['tenant_id'] = $event->mailLog->tenant_id;
        }

        $modelClass::firstOrCreate($attributes, [
            'reason' => $reason,
            'provider' => $event->trackingEvent->provider->value,
            'mail_log_id' => $event->mailLog->id,
            'suppressed_at' => now(),
        ]);
    }
}
