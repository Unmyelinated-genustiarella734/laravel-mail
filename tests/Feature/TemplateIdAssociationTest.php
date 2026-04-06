<?php

use JeffersonGoncalves\LaravelMail\Mail\TemplateNotificationMailable;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

it('sets mail_template_id on mail log when sent via TemplateMailable', function () {
    config()->set('mail.default', 'log');
    config()->set('laravel-mail.logging.enabled', true);

    $template = MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome Email',
        'subject' => ['en' => 'Welcome {{ $name }}!'],
        'html_body' => ['en' => '<h1>Hello {{ $name }}</h1>'],
        'is_active' => true,
        'variables' => [['name' => 'name', 'type' => 'string', 'example' => 'John']],
    ]);

    $mailable = new TemplateNotificationMailable('welcome', ['name' => 'Test User']);

    Mail::to('test@example.com')->send($mailable);

    $log = MailLog::first();

    expect($log)->not->toBeNull()
        ->and($log->mail_template_id)->toBe($template->id);
});

it('does not set mail_template_id when no template is used', function () {
    config()->set('mail.default', 'log');
    config()->set('laravel-mail.logging.enabled', true);

    Mail::raw('Plain text email', function ($message) {
        $message->to('test@example.com');
        $message->subject('No Template');
    });

    $log = MailLog::first();

    expect($log)->not->toBeNull()
        ->and($log->mail_template_id)->toBeNull();
});
