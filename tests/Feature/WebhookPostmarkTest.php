<?php

use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

beforeEach(function () {
    config()->set('laravel-mail.tracking.enabled', true);
    config()->set('laravel-mail.tracking.providers.postmark.enabled', true);
});

it('processes Postmark delivery event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'pm-delivery-123',
    ]);

    $payload = [
        'RecordType' => 'Delivery',
        'MessageID' => 'pm-delivery-123',
        'Recipient' => 'to@example.com',
        'DeliveredAt' => '2024-01-01T12:00:00Z',
    ];

    $response = $this->postJson('/webhooks/mail/postmark', $payload);

    $response->assertOk();
    expect(MailTrackingEvent::count())->toBe(1);

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Delivered)
        ->and($event->provider)->toBe(TrackingProvider::Postmark)
        ->and($event->recipient)->toBe('to@example.com');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Delivered);
});

it('processes Postmark bounce event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'bounce@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'pm-bounce-123',
    ]);

    $payload = [
        'RecordType' => 'Bounce',
        'MessageID' => 'pm-bounce-123',
        'Email' => 'bounce@example.com',
        'Type' => 'HardBounce',
        'TypeCode' => 1,
        'BouncedAt' => '2024-01-01T12:00:00Z',
    ];

    $this->postJson('/webhooks/mail/postmark', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Bounced)
        ->and($event->bounce_type)->toBe('HardBounce/1');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Bounced);
});

it('processes Postmark spam complaint event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'spam@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'pm-spam-123',
    ]);

    $payload = [
        'RecordType' => 'SpamComplaint',
        'MessageID' => 'pm-spam-123',
        'Email' => 'spam@example.com',
        'BouncedAt' => '2024-01-01T12:00:00Z',
    ];

    $this->postJson('/webhooks/mail/postmark', $payload)->assertOk();

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Complained);
});

it('processes Postmark open event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'pm-open-123',
    ]);

    $payload = [
        'RecordType' => 'Open',
        'MessageID' => 'pm-open-123',
        'Recipient' => 'to@example.com',
        'ReceivedAt' => '2024-01-01T12:00:00Z',
    ];

    $this->postJson('/webhooks/mail/postmark', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Opened);
});

it('processes Postmark click event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'pm-click-123',
    ]);

    $payload = [
        'RecordType' => 'Click',
        'MessageID' => 'pm-click-123',
        'Recipient' => 'to@example.com',
        'OriginalLink' => 'https://example.com/page',
        'ReceivedAt' => '2024-01-01T12:00:00Z',
    ];

    $this->postJson('/webhooks/mail/postmark', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Clicked)
        ->and($event->url)->toBe('https://example.com/page');
});

it('validates Postmark basic auth when configured', function () {
    config()->set('laravel-mail.tracking.providers.postmark.username', 'webhook_user');
    config()->set('laravel-mail.tracking.providers.postmark.password', 'webhook_pass');

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'pm-auth-123',
    ]);

    // Without auth - should fail
    $this->postJson('/webhooks/mail/postmark', [
        'RecordType' => 'Delivery',
        'MessageID' => 'pm-auth-123',
    ])->assertStatus(403);

    // With correct auth
    $this->withHeaders([
        'Authorization' => 'Basic '.base64_encode('webhook_user:webhook_pass'),
    ])->postJson('/webhooks/mail/postmark', [
        'RecordType' => 'Delivery',
        'MessageID' => 'pm-auth-123',
        'Recipient' => 'to@example.com',
        'DeliveredAt' => '2024-01-01T12:00:00Z',
    ])->assertOk();

    expect(MailTrackingEvent::count())->toBe(1);
});
