<?php

use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\LaravelMail\Http\Controllers\WebhookController;

Route::post('{provider}', [WebhookController::class, 'handle'])
    ->where('provider', 'ses|sendgrid|postmark|mailgun|resend')
    ->name('laravel-mail.webhook');
