<?php

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\SuppressionReason;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Events\MailBounced;
use JeffersonGoncalves\LaravelMail\Events\MailComplained;
use JeffersonGoncalves\LaravelMail\Listeners\AddToSuppressionList;
use JeffersonGoncalves\LaravelMail\Listeners\CheckSuppression;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailSuppression;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

beforeEach(function () {
    config()->set('laravel-mail.suppression.enabled', true);
});

it('auto-suppresses email on hard bounce', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'bounce@example.com', 'name' => '']],
        'status' => MailStatus::Bounced,
    ]);

    $trackingEvent = MailTrackingEvent::create([
        'mail_log_id' => $mailLog->id,
        'type' => TrackingEventType::Bounced,
        'provider' => TrackingProvider::Ses,
        'recipient' => 'bounce@example.com',
        'bounce_type' => 'Permanent/General',
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    $listener = new AddToSuppressionList;
    $listener->handle(new MailBounced($mailLog, $trackingEvent));

    expect(MailSuppression::count())->toBe(1);
    $suppression = MailSuppression::first();
    expect($suppression->email)->toBe('bounce@example.com')
        ->and($suppression->reason)->toBe(SuppressionReason::HardBounce);
});

it('does not suppress on soft bounce', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'soft@example.com', 'name' => '']],
        'status' => MailStatus::Bounced,
    ]);

    $trackingEvent = MailTrackingEvent::create([
        'mail_log_id' => $mailLog->id,
        'type' => TrackingEventType::Bounced,
        'provider' => TrackingProvider::Ses,
        'recipient' => 'soft@example.com',
        'bounce_type' => 'Transient/General',
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    $listener = new AddToSuppressionList;
    $listener->handle(new MailBounced($mailLog, $trackingEvent));

    expect(MailSuppression::count())->toBe(0);
});

it('auto-suppresses email on complaint', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'spam@example.com', 'name' => '']],
        'status' => MailStatus::Complained,
    ]);

    $trackingEvent = MailTrackingEvent::create([
        'mail_log_id' => $mailLog->id,
        'type' => TrackingEventType::Complained,
        'provider' => TrackingProvider::SendGrid,
        'recipient' => 'spam@example.com',
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    $listener = new AddToSuppressionList;
    $listener->handle(new MailComplained($mailLog, $trackingEvent));

    expect(MailSuppression::count())->toBe(1);
    expect(MailSuppression::first()->reason)->toBe(SuppressionReason::Complaint);
});

it('blocks sending to suppressed email', function () {
    Event::listen(MessageSending::class, CheckSuppression::class);

    MailSuppression::create([
        'email' => 'blocked@example.com',
        'reason' => SuppressionReason::HardBounce,
        'suppressed_at' => now(),
    ]);

    Mail::raw('Test', function ($message) {
        $message->to('blocked@example.com')
            ->from('from@example.com')
            ->subject('Should be blocked');
    });

    expect(MailLog::count())->toBe(0);
});

it('allows sending to non-suppressed email', function () {
    Mail::raw('Test', function ($message) {
        $message->to('allowed@example.com')
            ->from('from@example.com')
            ->subject('Should pass');
    });

    expect(MailLog::count())->toBe(1);
});

it('does not create duplicate suppressions', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'dup@example.com', 'name' => '']],
        'status' => MailStatus::Complained,
    ]);

    $trackingEvent = MailTrackingEvent::create([
        'mail_log_id' => $mailLog->id,
        'type' => TrackingEventType::Complained,
        'provider' => TrackingProvider::Resend,
        'recipient' => 'dup@example.com',
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    $listener = new AddToSuppressionList;
    $listener->handle(new MailComplained($mailLog, $trackingEvent));
    $listener->handle(new MailComplained($mailLog, $trackingEvent));

    expect(MailSuppression::count())->toBe(1);
});
