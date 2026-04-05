<?php

use JeffersonGoncalves\LaravelMail\Facades\LaravelMail;

it('resolves the facade', function () {
    expect(LaravelMail::isLoggingEnabled())->toBeTrue();
});

it('reports tracking as disabled by default', function () {
    expect(LaravelMail::isTrackingEnabled())->toBeFalse();
});

it('reports tracking as enabled when configured', function () {
    config()->set('laravel-mail.tracking.enabled', true);
    expect(LaravelMail::isTrackingEnabled())->toBeTrue();
});
