<?php

use JeffersonGoncalves\LaravelMail\Enums\SuppressionReason;
use JeffersonGoncalves\LaravelMail\Models\MailSuppression;

it('removes email from suppression list', function () {
    MailSuppression::create([
        'email' => 'test@example.com',
        'reason' => SuppressionReason::HardBounce,
        'suppressed_at' => now(),
    ]);

    expect(MailSuppression::count())->toBe(1);

    $this->artisan('mail:unsuppress test@example.com')
        ->expectsOutputToContain('Removed test@example.com')
        ->assertSuccessful();

    expect(MailSuppression::count())->toBe(0);
});

it('warns when email is not in suppression list', function () {
    $this->artisan('mail:unsuppress unknown@example.com')
        ->expectsOutputToContain('was not found')
        ->assertSuccessful();
});
