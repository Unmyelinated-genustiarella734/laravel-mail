<?php

namespace JeffersonGoncalves\LaravelMail\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Str;

class LogSendingMessage
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        $id = (string) Str::uuid();

        $message->getHeaders()->addTextHeader('X-LaravelMail-ID', $id);
    }
}
