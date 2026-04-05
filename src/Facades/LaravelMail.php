<?php

namespace JeffersonGoncalves\LaravelMail\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\LaravelMail\LaravelMail
 */
class LaravelMail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-mail';
    }
}
