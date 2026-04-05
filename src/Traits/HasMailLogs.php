<?php

namespace JeffersonGoncalves\LaravelMail\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

trait HasMailLogs
{
    public function mailLogs(): MorphMany
    {
        return $this->morphMany(
            config('laravel-mail.models.mail_log', MailLog::class),
            'mailable'
        );
    }
}
