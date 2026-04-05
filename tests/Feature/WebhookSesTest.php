<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

beforeEach(function () {
    config()->set('laravel-mail.tracking.enabled', true);
    config()->set('laravel-mail.tracking.providers.ses.enabled', true);
});

it('processes SES delivery notification', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'ses-message-id-123',
    ]);

    $payload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'notificationType' => 'Delivery',
            'mail' => [
                'messageId' => 'ses-message-id-123',
                'timestamp' => '2024-01-01T00:00:00.000Z',
            ],
            'delivery' => [
                'recipients' => ['to@example.com'],
            ],
        ]),
    ];

    $response = $this->postJson('/webhooks/mail/ses', $payload);

    $response->assertOk();
    expect(MailTrackingEvent::count())->toBe(1);

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Delivered)
        ->and($event->provider)->toBe(TrackingProvider::Ses)
        ->and($event->mail_log_id)->toBe($mailLog->id)
        ->and($event->recipient)->toBe('to@example.com');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Delivered);
});

it('processes SES bounce notification', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'bounce@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'ses-bounce-123',
    ]);

    $payload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'notificationType' => 'Bounce',
            'mail' => ['messageId' => 'ses-bounce-123'],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [
                    ['emailAddress' => 'bounce@example.com'],
                ],
            ],
        ]),
    ];

    $this->postJson('/webhooks/mail/ses', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Bounced)
        ->and($event->bounce_type)->toBe('Permanent/General');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Bounced);
});

it('processes SES complaint notification', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'complaint@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'ses-complaint-123',
    ]);

    $payload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'notificationType' => 'Complaint',
            'mail' => ['messageId' => 'ses-complaint-123'],
            'complaint' => [
                'complainedRecipients' => [
                    ['emailAddress' => 'complaint@example.com'],
                ],
            ],
        ]),
    ];

    $this->postJson('/webhooks/mail/ses', $payload)->assertOk();

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Complained);
});

it('handles SES subscription confirmation', function () {
    Http::fake(['*' => Http::response('OK')]);

    $payload = [
        'Type' => 'SubscriptionConfirmation',
        'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/confirm',
    ];

    $response = $this->postJson('/webhooks/mail/ses', $payload);

    $response->assertOk();
    expect(MailTrackingEvent::count())->toBe(0);

    Http::assertSent(fn ($request) => $request->url() === 'https://sns.us-east-1.amazonaws.com/confirm');
});

it('ignores SES notification for unknown message id', function () {
    $payload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'notificationType' => 'Delivery',
            'mail' => ['messageId' => 'unknown-id'],
            'delivery' => ['recipients' => ['test@example.com']],
        ]),
    ];

    $this->postJson('/webhooks/mail/ses', $payload)->assertOk();
    expect(MailTrackingEvent::count())->toBe(0);
});

it('rejects SES webhook with invalid payload', function () {
    $this->postJson('/webhooks/mail/ses', ['invalid' => 'data'])
        ->assertStatus(403);
});

it('returns 503 when tracking is disabled', function () {
    config()->set('laravel-mail.tracking.enabled', false);

    $this->postJson('/webhooks/mail/ses', ['Type' => 'Notification'])
        ->assertStatus(503);
});

it('returns 404 when provider is not enabled', function () {
    config()->set('laravel-mail.tracking.providers.ses.enabled', false);

    $this->postJson('/webhooks/mail/ses', ['Type' => 'Notification'])
        ->assertStatus(404);
});
