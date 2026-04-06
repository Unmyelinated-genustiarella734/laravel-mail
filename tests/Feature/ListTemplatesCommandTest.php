<?php

use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

it('lists all templates in a table', function () {
    MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome Email',
        'subject' => ['en' => 'Welcome!'],
        'html_body' => ['en' => '<h1>Welcome</h1>'],
        'is_active' => true,
    ]);

    MailTemplate::create([
        'key' => 'reset',
        'name' => 'Password Reset',
        'subject' => ['en' => 'Reset', 'pt_BR' => 'Resetar'],
        'html_body' => ['en' => '<p>Reset</p>', 'pt_BR' => '<p>Resetar</p>'],
        'is_active' => true,
    ]);

    $this->artisan('mail:templates')
        ->assertSuccessful()
        ->expectsOutputToContain('welcome')
        ->expectsOutputToContain('reset');
});

it('shows message when no templates exist', function () {
    $this->artisan('mail:templates')
        ->assertSuccessful()
        ->expectsOutputToContain('No templates found');
});

it('shows correct locale information', function () {
    MailTemplate::create([
        'key' => 'multi',
        'name' => 'Multi Locale',
        'subject' => ['en' => 'English', 'pt_BR' => 'Portugues'],
        'html_body' => ['en' => '<p>EN</p>', 'pt_BR' => '<p>PT</p>'],
        'is_active' => true,
    ]);

    $this->artisan('mail:templates')
        ->assertSuccessful()
        ->expectsOutputToContain('en, pt_BR');
});
