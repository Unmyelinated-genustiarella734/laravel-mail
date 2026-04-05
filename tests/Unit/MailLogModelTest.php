<?php

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

it('can create a mail log', function () {
    $log = MailLog::create([
        'mailer' => 'smtp',
        'subject' => 'Test Subject',
        'from' => [['email' => 'from@example.com', 'name' => 'Sender']],
        'to' => [['email' => 'to@example.com', 'name' => 'Recipient']],
        'status' => MailStatus::Sent,
    ]);

    expect($log)->toBeInstanceOf(MailLog::class)
        ->and($log->id)->toBeString()
        ->and($log->mailer)->toBe('smtp')
        ->and($log->subject)->toBe('Test Subject')
        ->and($log->from)->toBeArray()
        ->and($log->status)->toBe(MailStatus::Sent);
});

it('uses configured table name', function () {
    $log = new MailLog;
    expect($log->getTable())->toBe('mail_logs');

    config()->set('laravel-mail.database.tables.mail_logs', 'custom_mail_logs');
    expect($log->getTable())->toBe('custom_mail_logs');
});

it('has tracking events relationship', function () {
    $log = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    expect($log->trackingEvents())->toBeInstanceOf(HasMany::class);
});

it('has template relationship', function () {
    $log = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    expect($log->template())->toBeInstanceOf(BelongsTo::class);
});

it('casts json fields correctly', function () {
    $log = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'a@b.com', 'name' => 'A']],
        'to' => [['email' => 'c@d.com', 'name' => 'C']],
        'cc' => [['email' => 'e@f.com', 'name' => 'E']],
        'bcc' => [['email' => 'g@h.com', 'name' => 'G']],
        'reply_to' => [['email' => 'i@j.com', 'name' => 'I']],
        'headers' => ['X-Custom' => 'value'],
        'attachments' => [['filename' => 'file.pdf', 'size' => 1024]],
        'metadata' => ['key' => 'value'],
        'status' => MailStatus::Pending,
    ]);

    $log->refresh();

    expect($log->from)->toBeArray()
        ->and($log->to)->toBeArray()
        ->and($log->cc)->toBeArray()
        ->and($log->bcc)->toBeArray()
        ->and($log->reply_to)->toBeArray()
        ->and($log->headers)->toBeArray()
        ->and($log->attachments)->toBeArray()
        ->and($log->metadata)->toBeArray();
});
