<?php

namespace JeffersonGoncalves\LaravelMail\Listeners;

use Illuminate\Mail\Events\MessageSending;
use JeffersonGoncalves\LaravelMail\Models\MailSuppression;
use Symfony\Component\Mime\Address;

class CheckSuppression
{
    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;
        $recipients = $message->getTo();

        if (empty($recipients)) {
            return null;
        }

        $emails = array_map(fn (Address $address) => $address->getAddress(), $recipients);

        $modelClass = config('laravel-mail.models.mail_suppression', MailSuppression::class);

        $query = $modelClass::whereIn('email', $emails);

        if (config('laravel-mail.tenant.enabled', false)) {
            $tenantId = $event->data['__tenant_id'] ?? null;
            if ($tenantId) {
                $query->where(config('laravel-mail.tenant.column', 'tenant_id'), $tenantId);
            }
        }

        if ($query->exists()) {
            return false;
        }

        return null;
    }
}
