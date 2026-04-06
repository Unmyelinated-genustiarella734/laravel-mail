<?php

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Channels\TemplateMailChannel;
use JeffersonGoncalves\LaravelMail\Mail\TemplateNotificationMailable;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

class TestNotifiable
{
    public function routeNotificationFor(string $driver, $notification = null): string
    {
        return 'user@example.com';
    }
}

class TestTemplateNotification extends Notification
{
    public function via($notifiable): array
    {
        return [TemplateMailChannel::class];
    }

    public function toTemplateMail($notifiable): array
    {
        return [
            'template_key' => 'notification-test',
            'data' => ['name' => 'Alice'],
            'locale' => 'en',
        ];
    }
}

it('sends email via template mail channel', function () {
    Mail::fake();

    MailTemplate::create([
        'key' => 'notification-test',
        'name' => 'Notification Test',
        'subject' => ['en' => 'Hello {{ $name }}'],
        'html_body' => ['en' => '<p>Welcome {{ $name }}</p>'],
        'is_active' => true,
    ]);

    $channel = new TemplateMailChannel;
    $channel->send(new TestNotifiable, new TestTemplateNotification);

    Mail::assertSent(TemplateNotificationMailable::class, function ($mailable) {
        return $mailable->templateKey() === 'notification-test';
    });
});

it('does not send when recipient is null', function () {
    Mail::fake();

    $notifiable = new class
    {
        public function routeNotificationFor(string $driver, $notification = null): ?string
        {
            return null;
        }
    };

    $channel = new TemplateMailChannel;
    $channel->send($notifiable, new TestTemplateNotification);

    Mail::assertNothingSent();
});
