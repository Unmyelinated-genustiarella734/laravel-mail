<?php

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Events\MailBounced;
use JeffersonGoncalves\LaravelMail\Events\MailClicked;
use JeffersonGoncalves\LaravelMail\Events\MailComplained;
use JeffersonGoncalves\LaravelMail\Events\MailDeferred;
use JeffersonGoncalves\LaravelMail\Events\MailDelivered;
use JeffersonGoncalves\LaravelMail\Events\MailOpened;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

beforeEach(function () {
    config()->set('laravel-mail.tracking.enabled', true);
    config()->set('laravel-mail.tracking.providers.resend.enabled', true);
});

it('dispatches MailDelivered event on delivery webhook', function () {
    Event::fake([MailDelivered::class]);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'evt-delivered-123',
    ]);

    $this->postJson('/webhooks/mail/resend', [
        'type' => 'email.delivered',
        'data' => ['email_id' => 'evt-delivered-123', 'to' => ['to@example.com'], 'created_at' => now()->toIso8601String()],
    ])->assertOk();

    Event::assertDispatched(MailDelivered::class, function ($event) use ($mailLog) {
        return $event->mailLog->id === $mailLog->id
            && $event->trackingEvent->mail_log_id === $mailLog->id;
    });
});

it('dispatches MailBounced event on bounce webhook', function () {
    Event::fake([MailBounced::class]);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'evt-bounced-123',
    ]);

    $this->postJson('/webhooks/mail/resend', [
        'type' => 'email.bounced',
        'data' => ['email_id' => 'evt-bounced-123', 'to' => ['to@example.com'], 'bounce_type' => 'hard', 'created_at' => now()->toIso8601String()],
    ])->assertOk();

    Event::assertDispatched(MailBounced::class);
});

it('dispatches MailComplained event on complaint webhook', function () {
    Event::fake([MailComplained::class]);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'evt-complained-123',
    ]);

    $this->postJson('/webhooks/mail/resend', [
        'type' => 'email.complained',
        'data' => ['email_id' => 'evt-complained-123', 'to' => ['to@example.com'], 'created_at' => now()->toIso8601String()],
    ])->assertOk();

    Event::assertDispatched(MailComplained::class);
});

it('dispatches MailOpened event on open webhook', function () {
    Event::fake([MailOpened::class]);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'evt-opened-123',
    ]);

    $this->postJson('/webhooks/mail/resend', [
        'type' => 'email.opened',
        'data' => ['email_id' => 'evt-opened-123', 'to' => ['to@example.com'], 'created_at' => now()->toIso8601String()],
    ])->assertOk();

    Event::assertDispatched(MailOpened::class);
});

it('dispatches MailClicked event on click webhook', function () {
    Event::fake([MailClicked::class]);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'evt-clicked-123',
    ]);

    $this->postJson('/webhooks/mail/resend', [
        'type' => 'email.clicked',
        'data' => ['email_id' => 'evt-clicked-123', 'to' => ['to@example.com'], 'click' => ['link' => 'https://example.com'], 'created_at' => now()->toIso8601String()],
    ])->assertOk();

    Event::assertDispatched(MailClicked::class);
});

it('dispatches MailDeferred event on delay webhook', function () {
    Event::fake([MailDeferred::class]);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'evt-deferred-123',
    ]);

    $this->postJson('/webhooks/mail/resend', [
        'type' => 'email.delivery_delayed',
        'data' => ['email_id' => 'evt-deferred-123', 'to' => ['to@example.com'], 'created_at' => now()->toIso8601String()],
    ])->assertOk();

    Event::assertDispatched(MailDeferred::class);
});
