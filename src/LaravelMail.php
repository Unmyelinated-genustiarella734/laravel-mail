<?php

namespace JeffersonGoncalves\LaravelMail;

use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

class LaravelMail
{
    public function log(): MailLog
    {
        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        return new $modelClass;
    }

    public function findByProviderMessageId(string $messageId): ?MailLog
    {
        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        return $modelClass::where('provider_message_id', $messageId)->first();
    }

    public function updateStatus(MailLog $mailLog, MailStatus $status): MailLog
    {
        $mailLog->update(['status' => $status]);

        return $mailLog;
    }

    public function isLoggingEnabled(): bool
    {
        return (bool) config('laravel-mail.logging.enabled', true);
    }

    public function isTrackingEnabled(): bool
    {
        return (bool) config('laravel-mail.tracking.enabled', false);
    }
}
