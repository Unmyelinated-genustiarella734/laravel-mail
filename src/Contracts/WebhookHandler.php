<?php

namespace JeffersonGoncalves\LaravelMail\Contracts;

use Illuminate\Http\Request;

interface WebhookHandler
{
    public function validate(Request $request): bool;

    public function handle(Request $request): void;
}
