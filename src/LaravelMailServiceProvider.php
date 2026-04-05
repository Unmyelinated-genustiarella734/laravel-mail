<?php

namespace JeffersonGoncalves\LaravelMail;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\LaravelMail\Commands\PruneMailLogsCommand;
use JeffersonGoncalves\LaravelMail\Listeners\LogSendingMessage;
use JeffersonGoncalves\LaravelMail\Listeners\LogSentMessage;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMailServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mail')
            ->hasConfigFile()
            ->hasCommand(PruneMailLogsCommand::class)
            ->hasMigrations([
                'create_mail_logs_table',
                'create_mail_templates_table',
                'create_mail_template_versions_table',
                'create_mail_tracking_events_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('laravel-mail', function () {
            return new LaravelMail;
        });
    }

    public function packageBooted(): void
    {
        if (config('laravel-mail.logging.enabled', true)) {
            Event::listen(MessageSending::class, LogSendingMessage::class);
            Event::listen(MessageSent::class, LogSentMessage::class);
        }

        $this->registerWebhookRoutes();
    }

    protected function registerWebhookRoutes(): void
    {
        Route::prefix(config('laravel-mail.tracking.route_prefix', 'webhooks/mail'))
            ->middleware(config('laravel-mail.tracking.route_middleware', []))
            ->group(__DIR__.'/../routes/webhooks.php');
    }
}
