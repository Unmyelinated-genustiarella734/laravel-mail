<div class="filament-hidden">

![Laravel Mail](https://raw.githubusercontent.com/jeffersongoncalves/laravel-mail/master/art/jeffersongoncalves-laravel-mail.jpg)

</div>

# Laravel Mail

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-mail.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-mail)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-mail/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-mail/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-mail/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-mail/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-mail.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-mail)

Complete email management for Laravel: logging, database templates with translation, delivery tracking via webhooks (SES, SendGrid, Postmark, Mailgun, Resend), suppression list, browser preview, statistics, notification channel, retry, and analytics.

## Features

- **Email Logging** — Automatically logs all outgoing emails via `MessageSent` event
- **Database Templates** — Store email templates with Blade rendering and multi-locale translation
- **Template Versioning** — Automatic version history on every content change
- **Delivery Tracking** — Webhook handlers for 5 providers (SES, SendGrid, Postmark, Mailgun, Resend) with HMAC validation
- **Tracking Events** — Laravel events dispatched on delivery, bounce, complaint, open, click, and deferral
- **Suppression List** — Auto-suppress hard bounces and complaints, block sending to suppressed addresses
- **Browser Preview** — View sent emails and templates in the browser via signed URLs
- **Statistics** — Query helpers for sent, delivered, bounced, opened, clicked counts and daily aggregations
- **Notification Channel** — Send database templates via Laravel Notifications
- **Retry Failed Emails** — Retry failed or soft-bounced emails with max attempts control
- **Resend Emails** — Resend any previously sent email from the log
- **Pruning** — Artisan command to clean up old mail logs
- **Polymorphic Association** — Associate mail logs with any model via `HasMailLogs` trait
- **Multi-Tenant** — Optional tenant scoping for all tables
- **Customizable** — Override models, table names, and database connection

## Installation

```bash
composer require jeffersongoncalves/laravel-mail
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="laravel-mail-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="laravel-mail-config"
```

## Email Logging

Logging is enabled by default. Every email sent through Laravel's `Mail` facade is automatically logged to the `mail_logs` table.

```php
// Just send emails normally — they are logged automatically
Mail::to('user@example.com')->send(new WelcomeMail($user));
```

Each log entry captures: mailer, subject, from/to/cc/bcc/reply-to, HTML body, text body, headers, attachments metadata, and provider message ID.

### Disable Logging

```env
LARAVEL_MAIL_LOGGING_ENABLED=false
```

### Control What Gets Stored

```env
LARAVEL_MAIL_STORE_HTML=true
LARAVEL_MAIL_STORE_TEXT=true
```

## Database Templates

Create email templates in the database with multi-locale support and Blade rendering.

### Creating a Template

```php
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

MailTemplate::create([
    'key' => 'welcome',
    'name' => 'Welcome Email',
    'subject' => ['en' => 'Welcome, {{ $name }}!', 'pt_BR' => 'Bem-vindo, {{ $name }}!'],
    'html_body' => [
        'en' => '<h1>Hello {{ $name }}</h1><p>Welcome to our platform.</p>',
        'pt_BR' => '<h1>Ola {{ $name }}</h1><p>Bem-vindo a nossa plataforma.</p>',
    ],
    'variables' => [
        ['name' => 'name', 'type' => 'string', 'example' => 'John'],
    ],
]);
```

### Using Templates in Mailables

Extend `TemplateMailable` to create mailables that fetch content from the database:

```php
use JeffersonGoncalves\LaravelMail\Mail\TemplateMailable;
use Illuminate\Mail\Mailables\Content;

class WelcomeEmail extends TemplateMailable
{
    public function __construct(
        public User $user,
    ) {}

    public function templateKey(): string
    {
        return 'welcome';
    }

    public function templateData(): array
    {
        return ['name' => $this->user->name];
    }

    protected function fallbackSubject(): string
    {
        return 'Welcome!';
    }

    protected function fallbackContent(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: ['user' => $this->user],
        );
    }
}
```

### Template Versioning

Every content change (subject, html_body, text_body) automatically creates a version snapshot:

```php
$template->update([
    'subject' => ['en' => 'Updated Welcome, {{ $name }}!'],
    'html_body' => ['en' => '<h1>New design for {{ $name }}</h1>'],
]);

// Version 2 is automatically created
$template->versions; // Collection of MailTemplateVersion
```

### Template Preview

Preview a template with example data without sending:

```php
use JeffersonGoncalves\LaravelMail\Actions\PreviewTemplateAction;

$action = new PreviewTemplateAction();
$preview = $action->execute($template, ['name' => 'Alice'], 'en');

// Returns: ['subject' => '...', 'html' => '...', 'text' => '...']
```

### Layouts

Wrap template content in a shared layout:

```php
// config/laravel-mail.php
'templates' => [
    'default_layout' => '<html><body>{!! $slot !!}</body></html>',
],

// Or per template
$template->update(['layout' => '<html><body>{!! $slot !!}</body></html>']);
```

## Delivery Tracking via Webhooks

Track email delivery status in real-time via provider webhooks. Supports: **Amazon SES**, **SendGrid**, **Postmark**, **Mailgun**, and **Resend**.

### Enable Tracking

```env
LARAVEL_MAIL_TRACKING_ENABLED=true
```

### Configure Provider

Enable the providers you use in `config/laravel-mail.php`:

```php
'tracking' => [
    'enabled' => true,
    'route_prefix' => 'webhooks/mail',

    'providers' => [
        'ses' => [
            'enabled' => true,
        ],
        'sendgrid' => [
            'enabled' => true,
            'signing_secret' => env('LARAVEL_MAIL_SENDGRID_SIGNING_SECRET'),
        ],
        'postmark' => [
            'enabled' => true,
            'username' => env('LARAVEL_MAIL_POSTMARK_WEBHOOK_USERNAME'),
            'password' => env('LARAVEL_MAIL_POSTMARK_WEBHOOK_PASSWORD'),
        ],
        'mailgun' => [
            'enabled' => true,
            'signing_key' => env('LARAVEL_MAIL_MAILGUN_SIGNING_KEY'),
        ],
        'resend' => [
            'enabled' => true,
            'signing_secret' => env('LARAVEL_MAIL_RESEND_SIGNING_SECRET'),
        ],
    ],
],
```

### Webhook URLs

Configure these URLs in your email provider's dashboard:

| Provider | Webhook URL |
|----------|-------------|
| Amazon SES | `https://yourapp.com/webhooks/mail/ses` |
| SendGrid | `https://yourapp.com/webhooks/mail/sendgrid` |
| Postmark | `https://yourapp.com/webhooks/mail/postmark` |
| Mailgun | `https://yourapp.com/webhooks/mail/mailgun` |
| Resend | `https://yourapp.com/webhooks/mail/resend` |

### Tracked Events

| Event | Description | Updates Status | Laravel Event |
|-------|-------------|----------------|---------------|
| `delivered` | Email successfully delivered | `sent` -> `delivered` | `MailDelivered` |
| `bounced` | Email bounced (hard/soft) | -> `bounced` | `MailBounced` |
| `complained` | Recipient marked as spam | -> `complained` | `MailComplained` |
| `opened` | Email was opened | No | `MailOpened` |
| `clicked` | Link was clicked | No | `MailClicked` |
| `deferred` | Delivery was delayed | No | `MailDeferred` |

### Listening to Tracking Events

React to delivery events in your application:

```php
use JeffersonGoncalves\LaravelMail\Events\MailBounced;
use JeffersonGoncalves\LaravelMail\Events\MailComplained;

// In a listener or EventServiceProvider
Event::listen(MailBounced::class, function (MailBounced $event) {
    // $event->mailLog — the MailLog model
    // $event->trackingEvent — the MailTrackingEvent model
    Log::warning("Email bounced: {$event->trackingEvent->recipient}");
});

Event::listen(MailComplained::class, function (MailComplained $event) {
    // Disable the user's account, send alert, etc.
});
```

### Signature Validation

Each provider uses its own authentication method:

- **SES** — SNS certificate URL verification
- **SendGrid** — Timestamp-based validation (ECDSA)
- **Postmark** — HTTP Basic Auth
- **Mailgun** — HMAC SHA256 signature
- **Resend** — Svix HMAC SHA256 signature

When no signing secret is configured, validation is skipped (useful for development).

## Suppression List

Automatically suppress email addresses that hard bounce or receive spam complaints. Suppressed addresses are blocked from receiving future emails.

### Enable Suppression

```env
LARAVEL_MAIL_SUPPRESSION_ENABLED=true
```

```php
// config/laravel-mail.php
'suppression' => [
    'enabled' => true,
    'auto_suppress_hard_bounces' => true,
    'auto_suppress_complaints' => true,
],
```

When enabled, the package automatically:
- Adds hard-bounced addresses to the suppression list
- Adds complained addresses to the suppression list
- Blocks sending to any suppressed address (cancels the email before it's sent)

### Manual Suppression Management

```php
use JeffersonGoncalves\LaravelMail\Models\MailSuppression;
use JeffersonGoncalves\LaravelMail\Enums\SuppressionReason;

// Manually suppress an address
MailSuppression::create([
    'email' => 'user@example.com',
    'reason' => SuppressionReason::Manual,
    'suppressed_at' => now(),
]);

// Check if an address is suppressed
$isSuppressed = MailSuppression::where('email', 'user@example.com')->exists();
```

### Unsuppress Command

```bash
php artisan mail:unsuppress user@example.com
```

## Browser Preview

View sent emails and templates directly in the browser.

### Enable Preview

```env
LARAVEL_MAIL_PREVIEW_ENABLED=true
```

```php
// config/laravel-mail.php
'preview' => [
    'enabled' => true,
    'route_prefix' => 'mail/preview',
    'route_middleware' => ['web'],
    'signed_urls' => true, // Require signed URLs for security
],
```

### Preview URLs

Access preview URLs via model accessors:

```php
$mailLog->preview_url;    // GET /mail/preview/mail-log/{id}?signature=...
$template->preview_url;   // GET /mail/preview/template/{id}?signature=...
```

When `signed_urls` is enabled, URLs are cryptographically signed and cannot be tampered with. When disabled, plain URLs are generated.

## Statistics

Query email statistics with the `MailStats` facade:

```php
use JeffersonGoncalves\LaravelMail\Facades\MailStats;
use Illuminate\Support\Carbon;

$from = Carbon::now()->subDays(30);
$to = Carbon::now();

MailStats::sent($from, $to);          // int
MailStats::delivered($from, $to);     // int
MailStats::bounced($from, $to);       // int
MailStats::complained($from, $to);    // int
MailStats::opened($from, $to);        // int (from tracking events)
MailStats::clicked($from, $to);       // int (from tracking events)
MailStats::deliveryRate($from, $to);  // float (percentage)
MailStats::bounceRate($from, $to);    // float (percentage)
MailStats::dailyStats($from, $to);    // Collection of daily aggregations
```

## Notification Channel

Send database templates via Laravel Notifications:

```php
use JeffersonGoncalves\LaravelMail\Channels\TemplateMailChannel;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function via($notifiable): array
    {
        return [TemplateMailChannel::class];
    }

    public function toTemplateMail($notifiable): array
    {
        return [
            'template_key' => 'welcome',
            'data' => ['name' => $notifiable->name],
            'locale' => 'en',
        ];
    }
}
```

## Retry Failed Emails

Retry emails that failed to send or soft-bounced.

### Enable Retry

```php
// config/laravel-mail.php
'retry' => [
    'enabled' => true,
    'max_attempts' => 3,
],
```

### Retry Programmatically

```php
use JeffersonGoncalves\LaravelMail\Actions\RetryFailedMailAction;

$action = new RetryFailedMailAction();
$action->execute($failedMailLog); // Returns true on success, false if max attempts reached
```

### Retry via Command

```bash
# Retry failed emails from the last 24 hours
php artisan mail:retry

# Retry soft-bounced emails from the last 48 hours
php artisan mail:retry --status=bounced --hours=48

# Limit the number of retries
php artisan mail:retry --limit=50
```

Hard bounces are automatically skipped when retrying bounced emails.

## Resend Emails

Resend any previously logged email:

```php
use JeffersonGoncalves\LaravelMail\Actions\ResendMailAction;

$action = new ResendMailAction();
$action->execute($mailLog);
```

## Pruning Old Logs

Clean up old mail logs:

```bash
php artisan mail:prune            # Prune logs older than 30 days (default)
php artisan mail:prune --days=7   # Prune logs older than 7 days
```

Schedule it:

```php
Schedule::command('mail:prune')->daily();
```

## Polymorphic Association

Associate mail logs with any model:

```php
use JeffersonGoncalves\LaravelMail\Traits\HasMailLogs;

class User extends Model
{
    use HasMailLogs;
}

$user->mailLogs()->latest()->get();
```

## Multi-Tenancy

```php
// config/laravel-mail.php
'tenant' => [
    'enabled' => true,
    'column' => 'tenant_id',
],
```

## Custom Models

```php
// config/laravel-mail.php
'models' => [
    'mail_log' => \App\Models\MailLog::class,
    'mail_template' => \App\Models\MailTemplate::class,
    'mail_template_version' => \App\Models\MailTemplateVersion::class,
    'mail_tracking_event' => \App\Models\MailTrackingEvent::class,
    'mail_suppression' => \App\Models\MailSuppression::class,
],
```

## Custom Table Names

```php
// config/laravel-mail.php
'database' => [
    'connection' => null,
    'tables' => [
        'mail_logs' => 'mail_logs',
        'mail_templates' => 'mail_templates',
        'mail_template_versions' => 'mail_template_versions',
        'mail_tracking_events' => 'mail_tracking_events',
        'mail_suppressions' => 'mail_suppressions',
    ],
],
```

## Facades

```php
use JeffersonGoncalves\LaravelMail\Facades\LaravelMail;

LaravelMail::isLoggingEnabled();  // bool
LaravelMail::isTrackingEnabled(); // bool
LaravelMail::findByProviderMessageId('msg-id-123'); // ?MailLog
LaravelMail::updateStatus($mailLog, MailStatus::Delivered); // MailLog
```

```php
use JeffersonGoncalves\LaravelMail\Facades\MailStats;

MailStats::sent($from, $to);
MailStats::deliveryRate($from, $to);
MailStats::dailyStats($from, $to);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jefferson Goncalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
