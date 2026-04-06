<?php

use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;
use JeffersonGoncalves\LaravelMail\Services\MailStats;

function createLogWithStatus(MailStatus $status, ?Carbon $createdAt = null): MailLog
{
    $log = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => $status,
    ]);

    if ($createdAt) {
        MailLog::where('id', $log->id)->update(['created_at' => $createdAt]);
    }

    return $log;
}

it('counts sent emails', function () {
    $stats = app(MailStats::class);
    $from = Carbon::now()->subDay();
    $to = Carbon::now()->addDay();

    createLogWithStatus(MailStatus::Sent);
    createLogWithStatus(MailStatus::Delivered);
    createLogWithStatus(MailStatus::Pending);

    expect($stats->sent($from, $to))->toBe(2);
});

it('counts delivered emails', function () {
    $stats = app(MailStats::class);
    $from = Carbon::now()->subDay();
    $to = Carbon::now()->addDay();

    createLogWithStatus(MailStatus::Delivered);
    createLogWithStatus(MailStatus::Sent);

    expect($stats->delivered($from, $to))->toBe(1);
});

it('counts bounced emails', function () {
    $stats = app(MailStats::class);
    $from = Carbon::now()->subDay();
    $to = Carbon::now()->addDay();

    createLogWithStatus(MailStatus::Bounced);
    createLogWithStatus(MailStatus::Bounced);

    expect($stats->bounced($from, $to))->toBe(2);
});

it('counts opened emails from tracking events', function () {
    $stats = app(MailStats::class);
    $from = Carbon::now()->subDay();
    $to = Carbon::now()->addDay();

    $log = createLogWithStatus(MailStatus::Delivered);

    MailTrackingEvent::create([
        'mail_log_id' => $log->id,
        'type' => TrackingEventType::Opened,
        'provider' => TrackingProvider::Ses,
        'created_at' => now(),
    ]);

    expect($stats->opened($from, $to))->toBe(1);
});

it('calculates delivery rate', function () {
    $stats = app(MailStats::class);
    $from = Carbon::now()->subDay();
    $to = Carbon::now()->addDay();

    createLogWithStatus(MailStatus::Delivered);
    createLogWithStatus(MailStatus::Delivered);
    createLogWithStatus(MailStatus::Sent);
    createLogWithStatus(MailStatus::Bounced);

    expect($stats->deliveryRate($from, $to))->toBe(50.0);
});

it('returns zero delivery rate when no emails sent', function () {
    $stats = app(MailStats::class);
    $from = Carbon::now()->subDay();
    $to = Carbon::now()->addDay();

    expect($stats->deliveryRate($from, $to))->toBe(0.0);
});

it('returns daily stats', function () {
    $stats = app(MailStats::class);
    $from = Carbon::now()->subDays(2);
    $to = Carbon::now()->addDay();

    createLogWithStatus(MailStatus::Delivered);
    createLogWithStatus(MailStatus::Bounced);

    $daily = $stats->dailyStats($from, $to);
    expect($daily)->toHaveCount(1)
        ->and((int) $daily->first()->sent)->toBe(2);
});
