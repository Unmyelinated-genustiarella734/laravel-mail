<?php

namespace JeffersonGoncalves\LaravelMail\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Mail\TemplateNotificationMailable;

class TemplateMailChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTemplateMail')) {
            return;
        }

        /** @var array{template_key: string, data?: array<string, mixed>, locale?: string|null} $config */
        $config = $notification->toTemplateMail($notifiable);

        $recipient = $notifiable->routeNotificationFor('mail', $notification);

        if (! $recipient) {
            return;
        }

        $mailable = new TemplateNotificationMailable(
            key: $config['template_key'],
            data: $config['data'] ?? [],
        );

        $mailer = Mail::to($recipient);

        if (isset($config['locale'])) {
            $mailer = $mailer->locale($config['locale']);
        }

        $mailer->send($mailable);
    }
}
