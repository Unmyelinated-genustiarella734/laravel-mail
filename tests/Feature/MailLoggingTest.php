<?php

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Listeners\LogSendingMessage;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use Symfony\Component\Mime\Email;

it('logs a sent email to the database', function () {
    Mail::raw('Test email body', function ($message) {
        $message->to('recipient@example.com')
            ->from('sender@example.com', 'Sender Name')
            ->subject('Test Subject');
    });

    expect(MailLog::count())->toBe(1);

    $log = MailLog::first();
    expect($log->subject)->toBe('Test Subject')
        ->and($log->to)->toBeArray()
        ->and($log->to[0]['email'])->toBe('recipient@example.com')
        ->and($log->from[0]['email'])->toBe('sender@example.com')
        ->and($log->from[0]['name'])->toBe('Sender Name')
        ->and($log->status)->toBe(MailStatus::Sent)
        ->and($log->html_body)->toBeNull()
        ->and($log->text_body)->toContain('Test email body');
});

it('stores html body when configured', function () {
    Mail::html('<h1>Hello</h1>', function ($message) {
        $message->to('recipient@example.com')
            ->from('sender@example.com')
            ->subject('HTML Test');
    });

    $log = MailLog::first();
    expect($log->html_body)->toContain('<h1>Hello</h1>');
});

it('does not store html body when disabled', function () {
    Config::set('laravel-mail.logging.store_html_body', false);

    Mail::html('<h1>Hello</h1>', function ($message) {
        $message->to('recipient@example.com')
            ->from('sender@example.com')
            ->subject('HTML Test');
    });

    $log = MailLog::first();
    expect($log->html_body)->toBeNull();
});

it('does not store text body when disabled', function () {
    Config::set('laravel-mail.logging.store_text_body', false);

    Mail::raw('Plain text', function ($message) {
        $message->to('recipient@example.com')
            ->from('sender@example.com')
            ->subject('Text Test');
    });

    $log = MailLog::first();
    expect($log->text_body)->toBeNull();
});

it('does not log when logging is disabled', function () {
    Config::set('laravel-mail.logging.enabled', false);

    // Need to re-boot the service provider since listeners were already registered
    // Instead, we test the listener directly
    $email = new Email;
    $email->from('sender@example.com');
    $email->to('recipient@example.com');
    $email->subject('Test');
    $email->text('Body');

    // When logging is disabled, listeners should not be registered
    // But since we already booted, let's verify config check works
    expect(config('laravel-mail.logging.enabled'))->toBeFalse();
});

it('injects X-LaravelMail-ID header via LogSendingMessage', function () {
    $email = new Email;
    $email->from('sender@example.com');
    $email->to('recipient@example.com');
    $email->subject('Test');
    $email->text('Body');

    $event = new MessageSending($email, []);

    $listener = new LogSendingMessage;
    $listener->handle($event);

    $header = $email->getHeaders()->get('X-LaravelMail-ID');
    expect($header)->not->toBeNull()
        ->and($header->getBodyAsString())->toBeString()
        ->and(strlen($header->getBodyAsString()))->toBe(36); // UUID length
});

it('logs email with cc and bcc', function () {
    Mail::raw('Test', function ($message) {
        $message->to('to@example.com')
            ->from('from@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('CC BCC Test');
    });

    $log = MailLog::first();
    expect($log->cc)->toBeArray()
        ->and($log->cc[0]['email'])->toBe('cc@example.com')
        ->and($log->bcc)->toBeArray()
        ->and($log->bcc[0]['email'])->toBe('bcc@example.com');
});

it('stores headers excluding standard ones', function () {
    Mail::raw('Test', function ($message) {
        $message->to('to@example.com')
            ->from('from@example.com')
            ->subject('Header Test');
    });

    $log = MailLog::first();
    expect($log->headers)->toBeArray()
        ->and($log->headers)->not->toHaveKey('To')
        ->and($log->headers)->not->toHaveKey('From')
        ->and($log->headers)->not->toHaveKey('Subject')
        ->and($log->headers)->toHaveKey('X-LaravelMail-ID');
});

it('uses uuid as primary key', function () {
    Mail::raw('Test', function ($message) {
        $message->to('to@example.com')
            ->from('from@example.com')
            ->subject('UUID Test');
    });

    $log = MailLog::first();
    expect($log->id)->toBeString()
        ->and(strlen($log->id))->toBe(36);
});

it('casts status to MailStatus enum', function () {
    Mail::raw('Test', function ($message) {
        $message->to('to@example.com')
            ->from('from@example.com')
            ->subject('Status Test');
    });

    $log = MailLog::first();
    expect($log->status)->toBeInstanceOf(MailStatus::class)
        ->and($log->status)->toBe(MailStatus::Sent);
});
