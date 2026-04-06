<?php

namespace JeffersonGoncalves\LaravelMail\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\LaravelMail\Services\MailStats
 */
class MailStats extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-mail-stats';
    }
}
