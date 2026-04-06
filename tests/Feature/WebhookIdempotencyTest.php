<?php

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Events\MailDelivered;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

beforeEach(function () {
    config()->set('laravel-mail.tracking.enabled', true);
    config()->set('laravel-mail.tracking.providers.ses.enabled', true);
});

it('does not create duplicate tracking events for same provider event id', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'ses-msg-123',
    ]);

    $payload = [
        'Type' => 'Notification',
        'MessageId' => 'sns-notification-1',
        'Message' => json_encode([
            'notificationType' => 'Delivery',
            'mail' => ['messageId' => 'ses-msg-123', 'timestamp' => now()->toIso8601String()],
            'delivery' => ['recipients' => ['to@example.com']],
        ]),
    ];

    $this->postJson('/webhooks/mail/ses', $payload)->assertOk();
    $this->postJson('/webhooks/mail/ses', $payload)->assertOk();

    expect(MailTrackingEvent::count())->toBe(1);
});

it('dispatches event only once for duplicate webhooks', function () {
    Event::fake([MailDelivered::class]);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'ses-msg-456',
    ]);

    $payload = [
        'Type' => 'Notification',
        'MessageId' => 'sns-notification-2',
        'Message' => json_encode([
            'notificationType' => 'Delivery',
            'mail' => ['messageId' => 'ses-msg-456', 'timestamp' => now()->toIso8601String()],
            'delivery' => ['recipients' => ['to@example.com']],
        ]),
    ];

    $this->postJson('/webhooks/mail/ses', $payload);
    $this->postJson('/webhooks/mail/ses', $payload);

    Event::assertDispatchedTimes(MailDelivered::class, 1);
});

it('allows different event types for same mail log', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'ses-msg-789',
    ]);

    $deliveryPayload = [
        'Type' => 'Notification',
        'MessageId' => 'sns-delivery-1',
        'Message' => json_encode([
            'notificationType' => 'Delivery',
            'mail' => ['messageId' => 'ses-msg-789', 'timestamp' => now()->toIso8601String()],
            'delivery' => ['recipients' => ['to@example.com']],
        ]),
    ];

    $openPayload = [
        'Type' => 'Notification',
        'MessageId' => 'sns-open-1',
        'Message' => json_encode([
            'notificationType' => 'Open',
            'mail' => ['messageId' => 'ses-msg-789', 'timestamp' => now()->toIso8601String()],
            'open' => ['recipients' => ['to@example.com']],
        ]),
    ];

    $this->postJson('/webhooks/mail/ses', $deliveryPayload)->assertOk();
    $this->postJson('/webhooks/mail/ses', $openPayload)->assertOk();

    expect(MailTrackingEvent::count())->toBe(2);
});
