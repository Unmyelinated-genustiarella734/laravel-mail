<?php

use JeffersonGoncalves\LaravelMail\Models\MailTemplate;
use JeffersonGoncalves\LaravelMail\Models\MailTemplateVersion;

it('can create a mail template', function () {
    $template = MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome Email',
        'subject' => ['en' => 'Welcome!', 'pt_BR' => 'Bem-vindo!'],
        'html_body' => ['en' => '<h1>Welcome {{ $name }}</h1>', 'pt_BR' => '<h1>Bem-vindo {{ $name }}</h1>'],
        'variables' => [
            ['name' => 'name', 'type' => 'string', 'example' => 'John'],
        ],
        'is_active' => true,
    ]);

    expect($template)->toBeInstanceOf(MailTemplate::class)
        ->and($template->key)->toBe('welcome')
        ->and($template->subject)->toBeArray()
        ->and($template->is_active)->toBeTrue();
});

it('returns subject for locale', function () {
    $template = MailTemplate::create([
        'key' => 'test-locale',
        'name' => 'Test',
        'subject' => ['en' => 'Hello', 'pt_BR' => 'Olá'],
        'html_body' => ['en' => '<p>Hi</p>'],
    ]);

    expect($template->getSubjectForLocale('en'))->toBe('Hello')
        ->and($template->getSubjectForLocale('pt_BR'))->toBe('Olá');
});

it('falls back to first locale when requested locale is missing', function () {
    $template = MailTemplate::create([
        'key' => 'test-fallback',
        'name' => 'Test',
        'subject' => ['en' => 'Hello'],
        'html_body' => ['en' => '<p>Hi</p>'],
    ]);

    expect($template->getSubjectForLocale('fr'))->toBe('Hello');
});

it('has versions relationship', function () {
    $template = MailTemplate::create([
        'key' => 'test-versions',
        'name' => 'Test',
        'subject' => ['en' => 'Test'],
        'html_body' => ['en' => '<p>Test</p>'],
    ]);

    MailTemplateVersion::create([
        'mail_template_id' => $template->id,
        'subject' => ['en' => 'Test v1'],
        'html_body' => ['en' => '<p>Test v1</p>'],
        'version_number' => 1,
        'created_at' => now(),
    ]);

    // 1 from observer auto-version + 1 manual = 2 total
    expect($template->versions)->toHaveCount(2)
        ->and($template->versions->first()->version_number)->toBe(1);
});

it('returns html body for locale', function () {
    $template = MailTemplate::create([
        'key' => 'test-html',
        'name' => 'Test',
        'subject' => ['en' => 'Test'],
        'html_body' => ['en' => '<p>English</p>', 'pt_BR' => '<p>Português</p>'],
    ]);

    expect($template->getHtmlBodyForLocale('pt_BR'))->toBe('<p>Português</p>');
});
