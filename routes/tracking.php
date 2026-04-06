<?php

use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\LaravelMail\Http\Controllers\TrackingController;

Route::get('pixel/{mailLogId}', [TrackingController::class, 'pixel'])->name('laravel-mail.tracking.pixel');
Route::get('click/{mailLogId}', [TrackingController::class, 'click'])->name('laravel-mail.tracking.click');
