<?php

use JeffersonGoncalves\LaravelMail\Actions\RetryFailedMailAction;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

beforeEach(function () {
    config()->set('laravel-mail.retry.enabled', true);
});

it('retries a failed email', function () {
    $log = MailLog::create([
        'mailer' => 'log',
        'subject' => 'Failed Email',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'html_body' => '<p>Test</p>',
        'status' => MailStatus::Failed,
    ]);

    $action = new RetryFailedMailAction;
    $result = $action->execute($log);

    expect($result)->toBeTrue();

    $log->refresh();
    expect($log->metadata['retry_count'])->toBe(1)
        ->and($log->status)->toBe(MailStatus::Pending);
});

it('stops after max attempts', function () {
    $log = MailLog::create([
        'mailer' => 'log',
        'subject' => 'Max Retry',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'html_body' => '<p>Test</p>',
        'status' => MailStatus::Failed,
        'metadata' => ['retry_count' => 3],
    ]);

    $action = new RetryFailedMailAction;
    $result = $action->execute($log);

    expect($result)->toBeFalse();
});

it('returns false when retry is disabled', function () {
    config()->set('laravel-mail.retry.enabled', false);

    $log = MailLog::create([
        'mailer' => 'log',
        'subject' => 'Disabled Retry',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'html_body' => '<p>Test</p>',
        'status' => MailStatus::Failed,
    ]);

    $action = new RetryFailedMailAction;
    expect($action->execute($log))->toBeFalse();
});

it('retries via artisan command', function () {
    $log = MailLog::create([
        'mailer' => 'log',
        'subject' => 'Command Retry',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'html_body' => '<p>Test</p>',
        'status' => MailStatus::Failed,
    ]);

    $this->artisan('mail:retry')
        ->expectsOutputToContain('Retried 1')
        ->assertSuccessful();
});

it('skips hard bounces in retry command', function () {
    $log = MailLog::create([
        'mailer' => 'log',
        'subject' => 'Hard Bounce',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'html_body' => '<p>Test</p>',
        'status' => MailStatus::Bounced,
    ]);

    MailTrackingEvent::create([
        'mail_log_id' => $log->id,
        'type' => TrackingEventType::Bounced,
        'provider' => TrackingProvider::Ses,
        'bounce_type' => 'Permanent/General',
        'created_at' => now(),
    ]);

    $this->artisan('mail:retry --status=bounced')
        ->expectsOutputToContain('skipped 1')
        ->assertSuccessful();
});

it('reports when retry is disabled', function () {
    config()->set('laravel-mail.retry.enabled', false);

    $this->artisan('mail:retry')
        ->expectsOutputToContain('retry is disabled')
        ->assertSuccessful();
});
