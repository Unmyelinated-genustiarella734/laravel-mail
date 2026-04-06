<?php

use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Mail\TemplateNotificationMailable;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

beforeEach(function () {
    config()->set('mail.default', 'log');
    config()->set('laravel-mail.logging.enabled', true);
});

it('adds List-Unsubscribe header when enabled', function () {
    config()->set('laravel-mail.templates.unsubscribe.enabled', true);
    config()->set('laravel-mail.templates.unsubscribe.url', 'https://example.com/unsubscribe/{email}');
    config()->set('laravel-mail.templates.unsubscribe.mailto', 'unsubscribe@example.com');

    MailTemplate::create([
        'key' => 'unsub-test',
        'name' => 'Unsub Test',
        'subject' => ['en' => 'Test'],
        'html_body' => ['en' => '<p>Hello</p>'],
        'is_active' => true,
    ]);

    Mail::to('recipient@example.com')->send(new TemplateNotificationMailable('unsub-test'));

    $log = MailLog::first();

    expect($log->headers)->toHaveKey('List-Unsubscribe')
        ->and($log->headers['List-Unsubscribe'])->toContain('https://example.com/unsubscribe/')
        ->and($log->headers['List-Unsubscribe'])->toContain('mailto:unsubscribe@example.com')
        ->and($log->headers)->toHaveKey('List-Unsubscribe-Post');
});

it('does not add unsubscribe headers when disabled', function () {
    config()->set('laravel-mail.templates.unsubscribe.enabled', false);

    MailTemplate::create([
        'key' => 'no-unsub',
        'name' => 'No Unsub',
        'subject' => ['en' => 'Test'],
        'html_body' => ['en' => '<p>Hello</p>'],
        'is_active' => true,
    ]);

    Mail::to('recipient@example.com')->send(new TemplateNotificationMailable('no-unsub'));

    $log = MailLog::first();

    expect($log->headers)->not->toHaveKey('List-Unsubscribe');
});

it('substitutes email placeholder in unsubscribe URL', function () {
    config()->set('laravel-mail.templates.unsubscribe.enabled', true);
    config()->set('laravel-mail.templates.unsubscribe.url', 'https://example.com/unsubscribe/{email}');

    MailTemplate::create([
        'key' => 'placeholder-test',
        'name' => 'Placeholder Test',
        'subject' => ['en' => 'Test'],
        'html_body' => ['en' => '<p>Hello</p>'],
        'is_active' => true,
    ]);

    Mail::to('test@example.com')->send(new TemplateNotificationMailable('placeholder-test'));

    $log = MailLog::first();

    expect($log->headers['List-Unsubscribe'])->toContain(urlencode('test@example.com'));
});
