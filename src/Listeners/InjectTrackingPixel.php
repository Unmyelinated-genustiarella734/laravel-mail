<?php

namespace JeffersonGoncalves\LaravelMail\Listeners;

use Illuminate\Mail\Events\MessageSending;
use JeffersonGoncalves\LaravelMail\Services\PixelTracker;

class InjectTrackingPixel
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        $header = $message->getHeaders()->get('X-LaravelMail-ID');
        $mailLogId = $header?->getBodyAsString();

        if (! $mailLogId) {
            return;
        }

        $htmlBody = $message->getHtmlBody();

        if (! $htmlBody) {
            return;
        }

        $tracker = new PixelTracker;

        if (config('laravel-mail.tracking.pixel.open_tracking', false)) {
            $htmlBody = $tracker->injectTrackingPixel($htmlBody, $mailLogId);
        }

        if (config('laravel-mail.tracking.pixel.click_tracking', false)) {
            $htmlBody = $tracker->rewriteLinks($htmlBody, $mailLogId);
        }

        $message->html($htmlBody);
    }
}
