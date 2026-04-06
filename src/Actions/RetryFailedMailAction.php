<?php

namespace JeffersonGoncalves\LaravelMail\Actions;

use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

class RetryFailedMailAction
{
    public function execute(MailLog $mailLog): bool
    {
        if (! config('laravel-mail.retry.enabled', false)) {
            return false;
        }

        $maxAttempts = (int) config('laravel-mail.retry.max_attempts', 3);
        $metadata = $mailLog->metadata ?? [];
        $retryCount = (int) ($metadata['retry_count'] ?? 0);

        if ($retryCount >= $maxAttempts) {
            return false;
        }

        $resend = new ResendMailAction;
        $resend->execute($mailLog);

        $metadata['retry_count'] = $retryCount + 1;
        $metadata['last_retry_at'] = now()->toIso8601String();
        $mailLog->update([
            'metadata' => $metadata,
            'status' => MailStatus::Pending,
        ]);

        return true;
    }
}
