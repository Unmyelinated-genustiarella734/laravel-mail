<?php

use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

it('sends a test email using an existing template', function () {
    Mail::fake();

    MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome',
        'subject' => ['en' => 'Welcome!'],
        'html_body' => ['en' => '<h1>Hello</h1>'],
        'is_active' => true,
    ]);

    $this->artisan('mail:send-test', ['key' => 'welcome', 'email' => 'test@example.com'])
        ->assertSuccessful()
        ->expectsOutputToContain('Test email sent');
});

it('fails when template key does not exist', function () {
    $this->artisan('mail:send-test', ['key' => 'nonexistent', 'email' => 'test@example.com'])
        ->assertFailed()
        ->expectsOutputToContain('not found');
});

it('fails when template is inactive', function () {
    MailTemplate::create([
        'key' => 'inactive',
        'name' => 'Inactive',
        'subject' => ['en' => 'Test'],
        'html_body' => ['en' => '<p>Test</p>'],
        'is_active' => false,
    ]);

    $this->artisan('mail:send-test', ['key' => 'inactive', 'email' => 'test@example.com'])
        ->assertFailed();
});

it('uses example data from template variables as defaults', function () {
    Mail::fake();

    MailTemplate::create([
        'key' => 'greeting',
        'name' => 'Greeting',
        'subject' => ['en' => 'Hello {{ $name }}'],
        'html_body' => ['en' => '<h1>Hello {{ $name }}</h1>'],
        'is_active' => true,
        'variables' => [['name' => 'name', 'type' => 'string', 'example' => 'John']],
    ]);

    $this->artisan('mail:send-test', ['key' => 'greeting', 'email' => 'test@example.com'])
        ->assertSuccessful();
});

it('accepts custom data via --data option', function () {
    Mail::fake();

    MailTemplate::create([
        'key' => 'custom',
        'name' => 'Custom',
        'subject' => ['en' => 'Hi {{ $name }}'],
        'html_body' => ['en' => '<p>{{ $name }}</p>'],
        'is_active' => true,
    ]);

    $this->artisan('mail:send-test', [
        'key' => 'custom',
        'email' => 'test@example.com',
        '--data' => '{"name":"Alice"}',
    ])->assertSuccessful();
});
