<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

beforeEach(function () {
    config()->set('mail.default', 'log');
    config()->set('laravel-mail.logging.enabled', true);
    config()->set('laravel-mail.logging.store_attachments', true);
});

it('stores attachment files to disk when enabled', function () {
    Storage::fake('local');
    config()->set('laravel-mail.logging.store_attachment_files', true);
    config()->set('laravel-mail.logging.attachments_disk', 'local');

    Mail::raw('Email with attachment', function ($message) {
        $message->to('test@example.com');
        $message->subject('Attachment Test');
        $message->attachData('file content here', 'document.pdf', ['mime' => 'application/pdf']);
    });

    $log = MailLog::first();

    expect($log->attachments)->toBeArray()
        ->and($log->attachments[0])->toHaveKeys(['filename', 'content_type', 'size', 'path', 'disk'])
        ->and($log->attachments[0]['filename'])->toBe('document.pdf')
        ->and($log->attachments[0]['disk'])->toBe('local');

    Storage::disk('local')->assertExists($log->attachments[0]['path']);
});

it('does not store files when disabled', function () {
    Storage::fake('local');
    config()->set('laravel-mail.logging.store_attachment_files', false);

    Mail::raw('Email with attachment', function ($message) {
        $message->to('test@example.com');
        $message->subject('No File Store');
        $message->attachData('file content', 'test.txt', ['mime' => 'text/plain']);
    });

    $log = MailLog::first();

    expect($log->attachments)->toBeArray()
        ->and($log->attachments[0])->not->toHaveKey('path');
});

it('cleans up stored attachment files when pruning', function () {
    Storage::fake('local');
    config()->set('laravel-mail.logging.store_attachment_files', true);
    config()->set('laravel-mail.logging.attachments_disk', 'local');
    config()->set('laravel-mail.prune.enabled', true);

    $path = 'mail-attachments/2026/01/01/test-doc.pdf';
    Storage::disk('local')->put($path, 'content');

    $log = MailLog::create([
        'subject' => 'Old Email',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => 'sent',
        'attachments' => [['filename' => 'test-doc.pdf', 'path' => $path, 'disk' => 'local']],
    ]);
    MailLog::where('id', $log->id)->update(['created_at' => now()->subDays(60)]);

    $this->artisan('mail:prune', ['--days' => 30]);

    expect(MailLog::count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});
