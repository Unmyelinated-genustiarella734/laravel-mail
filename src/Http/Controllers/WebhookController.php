<?php

namespace JeffersonGoncalves\LaravelMail\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JeffersonGoncalves\LaravelMail\Contracts\WebhookHandler;
use JeffersonGoncalves\LaravelMail\Webhooks\MailgunWebhookHandler;
use JeffersonGoncalves\LaravelMail\Webhooks\PostmarkWebhookHandler;
use JeffersonGoncalves\LaravelMail\Webhooks\ResendWebhookHandler;
use JeffersonGoncalves\LaravelMail\Webhooks\SendGridWebhookHandler;
use JeffersonGoncalves\LaravelMail\Webhooks\SesWebhookHandler;

class WebhookController extends Controller
{
    /**
     * @var array<string, class-string<WebhookHandler>>
     */
    protected array $handlers = [
        'ses' => SesWebhookHandler::class,
        'sendgrid' => SendGridWebhookHandler::class,
        'postmark' => PostmarkWebhookHandler::class,
        'mailgun' => MailgunWebhookHandler::class,
        'resend' => ResendWebhookHandler::class,
    ];

    public function handle(Request $request, string $provider): JsonResponse
    {
        if (! config('laravel-mail.tracking.enabled', false)) {
            return response()->json(['message' => 'Tracking disabled'], 503);
        }

        if (! config("laravel-mail.tracking.providers.{$provider}.enabled", false)) {
            return response()->json(['message' => 'Provider not enabled'], 404);
        }

        $handlerClass = $this->handlers[$provider] ?? null;

        if (! $handlerClass) {
            return response()->json(['message' => 'Unknown provider'], 404);
        }

        $handler = app($handlerClass);

        if (! $handler->validate($request)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $handler->handle($request);

        return response()->json(['message' => 'OK']);
    }
}
