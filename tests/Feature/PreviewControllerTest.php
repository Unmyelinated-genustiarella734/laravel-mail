<?php

use Illuminate\Support\Facades\URL;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

beforeEach(function () {
    config()->set('laravel-mail.preview.enabled', true);
    config()->set('laravel-mail.preview.signed_urls', false);
});

it('renders mail log html in browser', function () {
    $log = MailLog::create([
        'subject' => 'Preview Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'html_body' => '<h1>Hello World</h1>',
        'status' => MailStatus::Sent,
    ]);

    $response = $this->get("/mail/preview/mail-log/{$log->id}");

    $response->assertOk()
        ->assertSee('<h1>Hello World</h1>', false);
});

it('renders template preview with example data', function () {
    $template = MailTemplate::create([
        'key' => 'browser-preview',
        'name' => 'Browser Preview',
        'subject' => ['en' => 'Hello {{ $name }}'],
        'html_body' => ['en' => '<h1>Welcome {{ $name }}</h1>'],
        'variables' => [
            ['name' => 'name', 'type' => 'string', 'example' => 'Alice'],
        ],
    ]);

    $response = $this->get("/mail/preview/template/{$template->id}");

    $response->assertOk()
        ->assertSee('Welcome Alice', false);
});

it('returns 404 for missing mail log', function () {
    $this->get('/mail/preview/mail-log/nonexistent-uuid')
        ->assertNotFound();
});

it('returns 404 for missing template', function () {
    $this->get('/mail/preview/template/nonexistent-uuid')
        ->assertNotFound();
});

it('validates signed url when enabled', function () {
    config()->set('laravel-mail.preview.signed_urls', true);

    $log = MailLog::create([
        'subject' => 'Signed Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'html_body' => '<p>Signed</p>',
        'status' => MailStatus::Sent,
    ]);

    // Without signature - should fail
    $this->get("/mail/preview/mail-log/{$log->id}")
        ->assertForbidden();

    // With valid signature
    $signedUrl = URL::signedRoute('laravel-mail.preview.mail-log', ['mailLog' => $log->id]);
    $path = parse_url($signedUrl, PHP_URL_PATH).'?'.parse_url($signedUrl, PHP_URL_QUERY);

    $this->get($path)->assertOk();
});

it('shows fallback when no html body', function () {
    $log = MailLog::create([
        'subject' => 'No Body',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    $response = $this->get("/mail/preview/mail-log/{$log->id}");

    $response->assertOk()
        ->assertSee('No HTML body available', false);
});

it('generates preview_url accessor on MailLog', function () {
    $log = MailLog::create([
        'subject' => 'Accessor Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    expect($log->preview_url)->not->toBeNull()
        ->and($log->preview_url)->toContain('mail/preview/mail-log');
});

it('returns null preview_url when preview disabled', function () {
    config()->set('laravel-mail.preview.enabled', false);

    $log = MailLog::create([
        'subject' => 'Disabled Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    expect($log->preview_url)->toBeNull();
});
