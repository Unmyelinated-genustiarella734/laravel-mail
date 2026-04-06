<?php

use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

it('returns 503 when tracking is disabled via webhook controller', function () {
    config()->set('laravel-mail.tracking.enabled', false);

    $this->postJson('/webhooks/mail/ses', ['Type' => 'Notification'])
        ->assertStatus(503);
});

it('returns 404 when provider is not enabled', function () {
    config()->set('laravel-mail.tracking.enabled', true);
    config()->set('laravel-mail.tracking.providers.ses.enabled', false);

    $this->postJson('/webhooks/mail/ses', ['Type' => 'Notification'])
        ->assertStatus(404);
});

it('returns 404 for preview when preview is disabled', function () {
    config()->set('laravel-mail.preview.enabled', false);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'html_body' => '<p>Test</p>',
    ]);

    $this->get("/mail/preview/mail-log/{$mailLog->id}")
        ->assertStatus(404);
});

it('allows preview when preview is enabled', function () {
    config()->set('laravel-mail.preview.enabled', true);
    config()->set('laravel-mail.preview.signed_urls', false);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'html_body' => '<p>Test</p>',
    ]);

    $this->get("/mail/preview/mail-log/{$mailLog->id}")
        ->assertOk();
});
