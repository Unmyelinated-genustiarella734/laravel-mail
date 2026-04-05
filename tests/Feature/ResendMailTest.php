<?php

use JeffersonGoncalves\LaravelMail\Actions\ResendMailAction;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

it('resends an email from mail log', function () {
    $log = MailLog::create([
        'mailer' => 'smtp',
        'subject' => 'Test Resend',
        'from' => [['email' => 'sender@example.com', 'name' => 'Sender']],
        'to' => [['email' => 'recipient@example.com', 'name' => 'Recipient']],
        'html_body' => '<h1>Hello</h1>',
        'text_body' => 'Hello',
        'status' => MailStatus::Sent,
    ]);

    expect(MailLog::count())->toBe(1);

    $action = new ResendMailAction;
    $action->execute($log);

    // Resend triggers the logging listeners, creating a new MailLog
    expect(MailLog::count())->toBe(2);

    $resent = MailLog::where('id', '!=', $log->id)->first();
    expect($resent->subject)->toBe('Test Resend')
        ->and($resent->to[0]['email'])->toBe('recipient@example.com')
        ->and($resent->status)->toBe(MailStatus::Sent);
});

it('resends email with cc and bcc', function () {
    $log = MailLog::create([
        'subject' => 'CC BCC Resend',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'cc' => [['email' => 'cc@example.com', 'name' => '']],
        'bcc' => [['email' => 'bcc@example.com', 'name' => '']],
        'reply_to' => [['email' => 'reply@example.com', 'name' => '']],
        'html_body' => '<p>Test</p>',
        'status' => MailStatus::Sent,
    ]);

    $action = new ResendMailAction;
    $action->execute($log);

    $resent = MailLog::where('id', '!=', $log->id)->first();
    expect($resent)->not->toBeNull()
        ->and($resent->cc[0]['email'])->toBe('cc@example.com')
        ->and($resent->bcc[0]['email'])->toBe('bcc@example.com');
});
