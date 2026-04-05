<div class="filament-hidden">

![Laravel Mail](https://raw.githubusercontent.com/jeffersongoncalves/laravel-mail/master/art/jeffersongoncalves-laravel-mail.jpg)

</div>

# Laravel Mail

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-mail.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-mail)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-mail/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-mail/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-mail/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-mail/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-mail.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-mail)

Complete email management for Laravel: automatic logging, database templates with translation support, delivery tracking via webhooks (SES, SendGrid, Postmark, Mailgun, Resend), preview, resend, and analytics.

## Features

- **Email Logging** — Automatically logs all outgoing emails via `MessageSent` event
- **Database Templates** — Store email templates in the database with Blade rendering and multi-locale translation
- **Template Versioning** — Automatic version history on every content change
- **Delivery Tracking** — Webhook handlers for 5 providers (SES, SendGrid, Postmark, Mailgun, Resend) with HMAC validation
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
        'pt_BR' => '<h1>Olá {{ $name }}</h1><p>Bem-vindo à nossa plataforma.</p>',
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

    // Fallback when no template exists in the database
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

The mailable automatically:
- Fetches the template by key
- Renders Blade syntax in subject and body
- Respects the current application locale
- Falls back to the first available locale when the current one is missing
- Uses `fallbackSubject()` and `fallbackContent()` when no template exists

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

### Layouts

Wrap template content in a shared layout:

```php
// config/laravel-mail.php
'templates' => [
    'default_layout' => '<html><body>{{ $slot }}</body></html>',
],

// Or per template
$template->update(['layout' => '<html><body>{{ $slot }}</body></html>']);
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

| Event | Description | Updates Status |
|-------|-------------|----------------|
| `delivered` | Email successfully delivered | `sent` -> `delivered` |
| `bounced` | Email bounced (hard/soft) | -> `bounced` |
| `complained` | Recipient marked as spam | -> `complained` |
| `opened` | Email was opened | No |
| `clicked` | Link was clicked | No |
| `deferred` | Delivery was delayed | No |

### Signature Validation

Each provider uses its own authentication method:

- **SES** — SNS certificate URL verification
- **SendGrid** — Timestamp-based validation (ECDSA)
- **Postmark** — HTTP Basic Auth
- **Mailgun** — HMAC SHA256 signature
- **Resend** — Svix HMAC SHA256 signature

When no signing secret is configured, validation is skipped (useful for development).

## Resend Emails

Resend any previously logged email:

```php
use JeffersonGoncalves\LaravelMail\Actions\ResendMailAction;

$mailLog = MailLog::find($id);
$action = new ResendMailAction();
$action->execute($mailLog);
```

## Pruning Old Logs

Clean up old mail logs with the artisan command:

```bash
# Prune logs older than 30 days (default)
php artisan mail:prune

# Prune logs older than 7 days
php artisan mail:prune --days=7
```

Schedule it in your `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('mail:prune')->daily();
```

### Disable Pruning

```php
// config/laravel-mail.php
'prune' => [
    'enabled' => false,
],
```

## Polymorphic Association

Associate mail logs with any model using the `HasMailLogs` trait:

```php
use JeffersonGoncalves\LaravelMail\Traits\HasMailLogs;

class User extends Model
{
    use HasMailLogs;
}

// Query mail logs for a user
$user->mailLogs()->latest()->get();
```

## Multi-Tenancy

Enable tenant scoping for all tables:

```php
// config/laravel-mail.php
'tenant' => [
    'enabled' => true,
    'column' => 'tenant_id',
],
```

Pass the tenant ID when sending emails via the `__tenant_id` data key:

```php
Mail::to('user@example.com')->send(new WelcomeMail($user), [
    '__tenant_id' => $tenant->id,
]);
```

## Custom Models

Override the default models to add custom behavior:

```php
// config/laravel-mail.php
'models' => [
    'mail_log' => \App\Models\MailLog::class,
    'mail_template' => \App\Models\MailTemplate::class,
    'mail_template_version' => \App\Models\MailTemplateVersion::class,
    'mail_tracking_event' => \App\Models\MailTrackingEvent::class,
],
```

## Custom Table Names

```php
// config/laravel-mail.php
'database' => [
    'connection' => null, // null uses default connection
    'tables' => [
        'mail_logs' => 'mail_logs',
        'mail_templates' => 'mail_templates',
        'mail_template_versions' => 'mail_template_versions',
        'mail_tracking_events' => 'mail_tracking_events',
    ],
],
```

## Facade

```php
use JeffersonGoncalves\LaravelMail\Facades\LaravelMail;

LaravelMail::isLoggingEnabled();  // bool
LaravelMail::isTrackingEnabled(); // bool
LaravelMail::findByProviderMessageId('msg-id-123'); // ?MailLog
LaravelMail::updateStatus($mailLog, MailStatus::Delivered); // MailLog
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
