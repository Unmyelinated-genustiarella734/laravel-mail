<?php

use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

beforeEach(function () {
    config()->set('laravel-mail.tracking.enabled', true);
    config()->set('laravel-mail.tracking.providers.mailgun.enabled', true);
});

it('processes Mailgun delivered event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'mg-delivered-123',
    ]);

    $payload = [
        'event-data' => [
            'event' => 'delivered',
            'recipient' => 'to@example.com',
            'timestamp' => time(),
            'message' => [
                'headers' => [
                    'message-id' => 'mg-delivered-123',
                ],
            ],
        ],
    ];

    $response = $this->postJson('/webhooks/mail/mailgun', $payload);

    $response->assertOk();
    expect(MailTrackingEvent::count())->toBe(1);

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Delivered)
        ->and($event->provider)->toBe(TrackingProvider::Mailgun)
        ->and($event->recipient)->toBe('to@example.com');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Delivered);
});

it('processes Mailgun failed event with bounce type', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'fail@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'mg-failed-123',
    ]);

    $payload = [
        'event-data' => [
            'event' => 'failed',
            'recipient' => 'fail@example.com',
            'severity' => 'permanent',
            'reason' => 'bounce',
            'timestamp' => time(),
            'message' => [
                'headers' => [
                    'message-id' => 'mg-failed-123',
                ],
            ],
        ],
    ];

    $this->postJson('/webhooks/mail/mailgun', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Bounced)
        ->and($event->bounce_type)->toBe('permanent/bounce');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Bounced);
});

it('processes Mailgun complained event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'spam@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'mg-complained-123',
    ]);

    $payload = [
        'event-data' => [
            'event' => 'complained',
            'recipient' => 'spam@example.com',
            'timestamp' => time(),
            'message' => [
                'headers' => [
                    'message-id' => 'mg-complained-123',
                ],
            ],
        ],
    ];

    $this->postJson('/webhooks/mail/mailgun', $payload)->assertOk();

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Complained);
});

it('processes Mailgun opened event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'mg-opened-123',
    ]);

    $payload = [
        'event-data' => [
            'event' => 'opened',
            'recipient' => 'to@example.com',
            'timestamp' => time(),
            'message' => [
                'headers' => [
                    'message-id' => 'mg-opened-123',
                ],
            ],
        ],
    ];

    $this->postJson('/webhooks/mail/mailgun', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Opened);
});

it('processes Mailgun clicked event with url', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'mg-clicked-123',
    ]);

    $payload = [
        'event-data' => [
            'event' => 'clicked',
            'recipient' => 'to@example.com',
            'url' => 'https://example.com/track',
            'timestamp' => time(),
            'message' => [
                'headers' => [
                    'message-id' => 'mg-clicked-123',
                ],
            ],
        ],
    ];

    $this->postJson('/webhooks/mail/mailgun', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Clicked)
        ->and($event->url)->toBe('https://example.com/track');
});

it('validates Mailgun HMAC signature when configured', function () {
    $signingKey = 'test-mailgun-signing-key';
    config()->set('laravel-mail.tracking.providers.mailgun.signing_key', $signingKey);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'mg-sig-123',
    ]);

    $timestamp = (string) time();
    $token = 'random-token-value';
    $expectedSignature = hash_hmac('sha256', $timestamp.$token, $signingKey);

    $payload = [
        'signature' => [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $expectedSignature,
        ],
        'event-data' => [
            'event' => 'delivered',
            'recipient' => 'to@example.com',
            'timestamp' => time(),
            'message' => [
                'headers' => [
                    'message-id' => 'mg-sig-123',
                ],
            ],
        ],
    ];

    $this->postJson('/webhooks/mail/mailgun', $payload)->assertOk();
    expect(MailTrackingEvent::count())->toBe(1);
});

it('rejects Mailgun webhook with invalid signature', function () {
    config()->set('laravel-mail.tracking.providers.mailgun.signing_key', 'valid-key');

    $payload = [
        'signature' => [
            'timestamp' => (string) time(),
            'token' => 'token',
            'signature' => 'invalid-signature',
        ],
        'event-data' => [
            'event' => 'delivered',
            'message' => ['headers' => ['message-id' => 'test']],
        ],
    ];

    $this->postJson('/webhooks/mail/mailgun', $payload)->assertStatus(403);
});
