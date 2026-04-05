<?php

use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

beforeEach(function () {
    config()->set('laravel-mail.tracking.enabled', true);
    config()->set('laravel-mail.tracking.providers.resend.enabled', true);
});

it('processes Resend delivered event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'resend-delivered-123',
    ]);

    $payload = [
        'type' => 'email.delivered',
        'data' => [
            'email_id' => 'resend-delivered-123',
            'to' => ['to@example.com'],
            'created_at' => '2024-01-01T12:00:00.000Z',
        ],
    ];

    $response = $this->postJson('/webhooks/mail/resend', $payload);

    $response->assertOk();
    expect(MailTrackingEvent::count())->toBe(1);

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Delivered)
        ->and($event->provider)->toBe(TrackingProvider::Resend)
        ->and($event->recipient)->toBe('to@example.com');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Delivered);
});

it('processes Resend bounced event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'bounce@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'resend-bounce-123',
    ]);

    $payload = [
        'type' => 'email.bounced',
        'data' => [
            'email_id' => 'resend-bounce-123',
            'to' => ['bounce@example.com'],
            'bounce_type' => 'hard',
            'created_at' => '2024-01-01T12:00:00.000Z',
        ],
    ];

    $this->postJson('/webhooks/mail/resend', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Bounced)
        ->and($event->bounce_type)->toBe('hard');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Bounced);
});

it('processes Resend complained event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'spam@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'resend-spam-123',
    ]);

    $payload = [
        'type' => 'email.complained',
        'data' => [
            'email_id' => 'resend-spam-123',
            'to' => ['spam@example.com'],
            'created_at' => '2024-01-01T12:00:00.000Z',
        ],
    ];

    $this->postJson('/webhooks/mail/resend', $payload)->assertOk();

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Complained);
});

it('processes Resend opened event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'resend-open-123',
    ]);

    $payload = [
        'type' => 'email.opened',
        'data' => [
            'email_id' => 'resend-open-123',
            'to' => ['to@example.com'],
            'created_at' => '2024-01-01T12:00:00.000Z',
        ],
    ];

    $this->postJson('/webhooks/mail/resend', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Opened);
});

it('processes Resend clicked event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'resend-click-123',
    ]);

    $payload = [
        'type' => 'email.clicked',
        'data' => [
            'email_id' => 'resend-click-123',
            'to' => ['to@example.com'],
            'click' => ['link' => 'https://example.com/page'],
            'created_at' => '2024-01-01T12:00:00.000Z',
        ],
    ];

    $this->postJson('/webhooks/mail/resend', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Clicked)
        ->and($event->url)->toBe('https://example.com/page');
});

it('processes Resend delivery delayed event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'resend-delay-123',
    ]);

    $payload = [
        'type' => 'email.delivery_delayed',
        'data' => [
            'email_id' => 'resend-delay-123',
            'to' => ['to@example.com'],
            'created_at' => '2024-01-01T12:00:00.000Z',
        ],
    ];

    $this->postJson('/webhooks/mail/resend', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Deferred);
});

it('validates Resend Svix signature when configured', function () {
    $secret = base64_encode('test-secret-key-32bytes!!!!!!!!!!');
    config()->set('laravel-mail.tracking.providers.resend.signing_secret', 'whsec_'.$secret);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'resend-sig-123',
    ]);

    $body = json_encode([
        'type' => 'email.delivered',
        'data' => [
            'email_id' => 'resend-sig-123',
            'to' => ['to@example.com'],
            'created_at' => '2024-01-01T12:00:00.000Z',
        ],
    ]);

    $messageId = 'msg_test123';
    $timestamp = (string) time();
    $toSign = "{$messageId}.{$timestamp}.{$body}";
    $secretBytes = base64_decode($secret);
    $signature = base64_encode(hash_hmac('sha256', $toSign, $secretBytes, true));

    $this->withHeaders([
        'svix-id' => $messageId,
        'svix-timestamp' => $timestamp,
        'svix-signature' => "v1,{$signature}",
    ])->postJson('/webhooks/mail/resend', json_decode($body, true))->assertOk();

    expect(MailTrackingEvent::count())->toBe(1);
});

it('rejects Resend webhook with missing signature headers', function () {
    config()->set('laravel-mail.tracking.providers.resend.signing_secret', 'whsec_test');

    $this->postJson('/webhooks/mail/resend', [
        'type' => 'email.delivered',
        'data' => ['email_id' => 'test'],
    ])->assertStatus(403);
});

it('ignores Resend event for unknown email id', function () {
    $payload = [
        'type' => 'email.delivered',
        'data' => [
            'email_id' => 'unknown-id',
            'to' => ['test@example.com'],
        ],
    ];

    $this->postJson('/webhooks/mail/resend', $payload)->assertOk();
    expect(MailTrackingEvent::count())->toBe(0);
});
