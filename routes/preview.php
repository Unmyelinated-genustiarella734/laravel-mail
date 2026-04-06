<?php

use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\LaravelMail\Http\Controllers\PreviewController;

Route::get('mail-log/{mailLog}', [PreviewController::class, 'showMailLog'])
    ->name('laravel-mail.preview.mail-log');

Route::get('template/{mailTemplate}', [PreviewController::class, 'showTemplate'])
    ->name('laravel-mail.preview.template');
