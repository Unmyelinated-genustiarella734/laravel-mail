<?php

use JeffersonGoncalves\LaravelMail\Actions\PreviewTemplateAction;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

it('inlines CSS in template HTML when enabled', function () {
    config()->set('laravel-mail.templates.inline_css', true);

    $template = MailTemplate::create([
        'key' => 'styled',
        'name' => 'Styled Email',
        'subject' => ['en' => 'Styled'],
        'html_body' => ['en' => '<style>h1 { color: red; }</style><h1>Hello</h1>'],
        'is_active' => true,
    ]);

    $action = new PreviewTemplateAction;
    $result = $action->execute($template);

    expect($result['html'])->toContain('style=');
});

it('does not inline CSS when disabled', function () {
    config()->set('laravel-mail.templates.inline_css', false);

    $template = MailTemplate::create([
        'key' => 'unstyled',
        'name' => 'Unstyled Email',
        'subject' => ['en' => 'Unstyled'],
        'html_body' => ['en' => '<style>h1 { color: red; }</style><h1>Hello</h1>'],
        'is_active' => true,
    ]);

    $action = new PreviewTemplateAction;
    $result = $action->execute($template);

    expect($result['html'])->toContain('<style>');
    expect($result['html'])->not->toContain('style="color: red');
});
