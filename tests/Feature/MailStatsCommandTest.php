<?php

use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

it('displays mail statistics', function () {
    MailLog::create([
        'subject' => 'Test 1',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
    ]);

    MailLog::create([
        'subject' => 'Test 2',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Bounced,
    ]);

    $this->artisan('mail:stats')
        ->assertSuccessful()
        ->expectsOutputToContain('Sent')
        ->expectsOutputToContain('Delivered')
        ->expectsOutputToContain('Bounced');
});

it('accepts custom days option', function () {
    MailLog::create([
        'subject' => 'Recent',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    $this->artisan('mail:stats', ['--days' => 3])
        ->assertSuccessful()
        ->expectsOutputToContain('last 3 days');
});

it('handles zero emails gracefully', function () {
    $this->artisan('mail:stats')
        ->assertSuccessful()
        ->expectsOutputToContain('0');
});
