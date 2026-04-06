<?php

use JeffersonGoncalves\LaravelMail\Actions\PreviewTemplateAction;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

it('renders template with example variables', function () {
    $template = MailTemplate::create([
        'key' => 'preview-test',
        'name' => 'Preview Test',
        'subject' => ['en' => 'Hello {{ $name }}'],
        'html_body' => ['en' => '<h1>Welcome {{ $name }}</h1>'],
        'text_body' => ['en' => 'Welcome {{ $name }}'],
        'variables' => [
            ['name' => 'name', 'type' => 'string', 'example' => 'John'],
        ],
    ]);

    $action = new PreviewTemplateAction;
    $result = $action->execute($template);

    expect($result['subject'])->toBe('Hello John')
        ->and($result['html'])->toContain('Welcome John')
        ->and($result['text'])->toBe('Welcome John');
});

it('overrides example data with provided data', function () {
    $template = MailTemplate::create([
        'key' => 'override-test',
        'name' => 'Override Test',
        'subject' => ['en' => 'Hi {{ $name }}'],
        'html_body' => ['en' => '<p>{{ $name }}</p>'],
        'variables' => [
            ['name' => 'name', 'type' => 'string', 'example' => 'Default'],
        ],
    ]);

    $action = new PreviewTemplateAction;
    $result = $action->execute($template, ['name' => 'Custom']);

    expect($result['subject'])->toBe('Hi Custom')
        ->and($result['html'])->toContain('Custom');
});

it('renders for specific locale', function () {
    $template = MailTemplate::create([
        'key' => 'locale-preview',
        'name' => 'Locale Preview',
        'subject' => ['en' => 'Hello', 'pt_BR' => 'Olá'],
        'html_body' => ['en' => '<p>Hello</p>', 'pt_BR' => '<p>Olá</p>'],
    ]);

    $action = new PreviewTemplateAction;
    $result = $action->execute($template, [], 'pt_BR');

    expect($result['subject'])->toBe('Olá')
        ->and($result['html'])->toContain('Olá');
});

it('wraps html in layout', function () {
    $template = MailTemplate::create([
        'key' => 'layout-preview',
        'name' => 'Layout Preview',
        'subject' => ['en' => 'Test'],
        'html_body' => ['en' => '<p>Content</p>'],
        'layout' => '<html><body>{!! $slot !!}</body></html>',
    ]);

    $action = new PreviewTemplateAction;
    $result = $action->execute($template);

    expect($result['html'])->toContain('<html><body>')
        ->and($result['html'])->toContain('<p>Content</p>');
});

it('returns null text when no text body', function () {
    $template = MailTemplate::create([
        'key' => 'no-text',
        'name' => 'No Text',
        'subject' => ['en' => 'Test'],
        'html_body' => ['en' => '<p>HTML only</p>'],
    ]);

    $action = new PreviewTemplateAction;
    $result = $action->execute($template);

    expect($result['text'])->toBeNull();
});
