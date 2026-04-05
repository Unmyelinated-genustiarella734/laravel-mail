<?php

use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

beforeEach(function () {
    config()->set('laravel-mail.tracking.enabled', true);
    config()->set('laravel-mail.tracking.providers.sendgrid.enabled', true);
});

it('processes SendGrid delivered event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'sg-message-123',
    ]);

    $payload = [
        [
            'event' => 'delivered',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-message-123.filter001',
            'timestamp' => time(),
        ],
    ];

    $response = $this->postJson('/webhooks/mail/sendgrid', $payload);

    $response->assertOk();
    expect(MailTrackingEvent::count())->toBe(1);

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Delivered)
        ->and($event->provider)->toBe(TrackingProvider::SendGrid)
        ->and($event->recipient)->toBe('to@example.com');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Delivered);
});

it('processes SendGrid bounce event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'bounce@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'sg-bounce-123',
    ]);

    $payload = [
        [
            'event' => 'bounce',
            'email' => 'bounce@example.com',
            'sg_message_id' => 'sg-bounce-123.filter',
            'type' => 'bounce',
            'timestamp' => time(),
        ],
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Bounced)
        ->and($event->bounce_type)->toBe('bounce');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Bounced);
});

it('processes SendGrid open event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'sg-open-123',
    ]);

    $payload = [
        [
            'event' => 'open',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-open-123.filter',
            'timestamp' => time(),
        ],
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Opened);

    // Open does not change mail log status
    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Delivered);
});

it('processes SendGrid click event with url', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'sg-click-123',
    ]);

    $payload = [
        [
            'event' => 'click',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-click-123.filter',
            'url' => 'https://example.com/link',
            'timestamp' => time(),
        ],
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Clicked)
        ->and($event->url)->toBe('https://example.com/link');
});

it('processes multiple SendGrid events in one request', function () {
    $mailLog1 = MailLog::create([
        'subject' => 'Test 1',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'sg-multi-1',
    ]);

    $mailLog2 = MailLog::create([
        'subject' => 'Test 2',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'other@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'sg-multi-2',
    ]);

    $payload = [
        [
            'event' => 'delivered',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-multi-1.filter',
            'timestamp' => time(),
        ],
        [
            'event' => 'delivered',
            'email' => 'other@example.com',
            'sg_message_id' => 'sg-multi-2.filter',
            'timestamp' => time(),
        ],
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload)->assertOk();

    expect(MailTrackingEvent::count())->toBe(2);
});
