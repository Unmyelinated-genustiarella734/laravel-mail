---
name: laravel-mail-development
description: Build and work with Laravel Mail features including email logging, database templates, webhook tracking, suppression lists, browser preview, statistics, notification channels, and retry mechanisms.
---

# Laravel Mail Development

## When to use this skill

Use this skill when:
- Setting up email logging and configuring what gets stored
- Creating database email templates with multi-locale support
- Configuring webhook delivery tracking for email providers
- Implementing suppression lists for bounced/complained addresses
- Setting up browser preview for sent emails and templates
- Querying email statistics and building dashboards
- Using the notification channel with database templates
- Configuring email retry for failed/bounced sends
- Associating mail logs with models via polymorphic relations

## Database Schema

### mail_logs
```
uuid id PK
string mailer
string subject
json from, to, cc, bcc, reply_to  -- [{email, name}]
longText html_body, text_body
json headers, attachments, metadata
string status (pending|sent|delivered|bounced|complained|failed)
string provider_message_id (indexed)
nullableUuidMorphs mailable (mailable_type, mailable_id)
foreignUuid mail_template_id
string tenant_id
timestamps
```

### mail_templates
```
uuid id PK
string key (unique)
string name
string mailable_class
json subject, html_body, text_body  -- keyed by locale {'en': '...', 'pt_BR': '...'}
json variables  -- [{name, type, example}]
string layout
string tenant_id
boolean is_active
timestamps
```

### mail_template_versions
```
uuid id PK
foreignUuid mail_template_id (cascade delete)
json subject, html_body, text_body
string change_note, author
unsignedInteger version_number
timestamp created_at
```

### mail_tracking_events
```
uuid id PK
foreignUuid mail_log_id (cascade delete)
string type (delivered|bounced|opened|clicked|complained|deferred)
string provider (ses|sendgrid|postmark|mailgun|resend)
json payload
string recipient, url, bounce_type
timestamp occurred_at, created_at
```

### mail_suppressions
```
uuid id PK
string email (indexed)
string reason (hard_bounce|complaint|manual)
string provider
foreignUuid mail_log_id (nullable)
timestamp suppressed_at
string tenant_id
unique(email, tenant_id)
```

## Creating Templates

```php
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

$template = MailTemplate::create([
    'key' => 'order-confirmation',
    'name' => 'Order Confirmation',
    'subject' => [
        'en' => 'Order #{{ $order_number }} confirmed',
        'pt_BR' => 'Pedido #{{ $order_number }} confirmado',
    ],
    'html_body' => [
        'en' => '<h1>Thank you, {{ $name }}</h1><p>Order #{{ $order_number }}</p>',
        'pt_BR' => '<h1>Obrigado, {{ $name }}</h1><p>Pedido #{{ $order_number }}</p>',
    ],
    'variables' => [
        ['name' => 'name', 'type' => 'string', 'example' => 'John'],
        ['name' => 'order_number', 'type' => 'string', 'example' => '12345'],
    ],
    'is_active' => true,
]);
```

## Creating a TemplateMailable

```php
use JeffersonGoncalves\LaravelMail\Mail\TemplateMailable;
use Illuminate\Mail\Mailables\Content;

class OrderConfirmationEmail extends TemplateMailable
{
    public function __construct(
        public Order $order,
    ) {}

    public function templateKey(): string
    {
        return 'order-confirmation';
    }

    public function templateData(): array
    {
        return [
            'name' => $this->order->customer->name,
            'order_number' => $this->order->number,
        ];
    }

    protected function fallbackSubject(): string
    {
        return "Order #{$this->order->number} confirmed";
    }

    protected function fallbackContent(): Content
    {
        return new Content(view: 'emails.order-confirmation', with: ['order' => $this->order]);
    }
}
```

## Listening to Tracking Events

```php
use JeffersonGoncalves\LaravelMail\Events\MailBounced;
use JeffersonGoncalves\LaravelMail\Events\MailDelivered;

// In EventServiceProvider or listener
Event::listen(MailBounced::class, function (MailBounced $event) {
    // $event->mailLog — the MailLog record
    // $event->trackingEvent — the MailTrackingEvent with bounce_type, recipient, etc.
    $recipient = $event->trackingEvent->recipient;
    $bounceType = $event->trackingEvent->bounce_type;
    Log::warning("Bounce ({$bounceType}): {$recipient}");
});
```

## Previewing Templates

```php
use JeffersonGoncalves\LaravelMail\Actions\PreviewTemplateAction;

$action = new PreviewTemplateAction();
$preview = $action->execute($template, ['name' => 'Alice'], 'en');
// Returns: ['subject' => 'rendered subject', 'html' => 'rendered html', 'text' => null]
```

## Using the Notification Channel

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
            'locale' => $notifiable->preferred_locale,
        ];
    }
}


// Send it
$user->notify(new WelcomeNotification());
```

## Retrying Failed Emails

```php
use JeffersonGoncalves\LaravelMail\Actions\RetryFailedMailAction;

$action = new RetryFailedMailAction();
$success = $action->execute($failedMailLog);
// Checks retry.max_attempts, resends via ResendMailAction, increments metadata.retry_count
```

## Polymorphic Association

```php
use JeffersonGoncalves\LaravelMail\Traits\HasMailLogs;

class User extends Model
{
    use HasMailLogs;
}

// Query
$user->mailLogs()->where('status', 'delivered')->latest()->get();
```

## Configuration Reference

| Key | Default | Description |
|-----|---------|-------------|
| `logging.enabled` | `true` | Enable email logging |
| `logging.store_html_body` | `true` | Store HTML body |
| `logging.store_text_body` | `true` | Store text body |
| `prune.enabled` | `true` | Enable pruning |
| `prune.older_than_days` | `30` | Days to keep logs |
| `tracking.enabled` | `false` | Enable webhook tracking |
| `suppression.enabled` | `false` | Enable suppression list |
| `suppression.auto_suppress_hard_bounces` | `true` | Auto-suppress hard bounces |
| `suppression.auto_suppress_complaints` | `true` | Auto-suppress complaints |
| `retry.enabled` | `false` | Enable retry |
| `retry.max_attempts` | `3` | Max retry attempts |
| `preview.enabled` | `false` | Enable browser preview |
| `preview.signed_urls` | `true` | Require signed URLs |
| `templates.enabled` | `true` | Enable database templates |
| `tenant.enabled` | `false` | Enable multi-tenancy |

## Troubleshooting

### Emails not being logged
**Cause**: Logging disabled or listeners not registered.
**Solution**: Check `LARAVEL_MAIL_LOGGING_ENABLED=true` in `.env`. Ensure the service provider is auto-discovered.

### Webhook returning 503
**Cause**: Tracking globally disabled.
**Solution**: Set `LARAVEL_MAIL_TRACKING_ENABLED=true` and enable the specific provider in config.

### Template not rendering variables
**Cause**: Template uses Blade syntax but data not passed.
**Solution**: Ensure `templateData()` returns all variables used in the template body. Check the template is `is_active`.

### Preview URL returns 404
**Cause**: Preview feature disabled.
**Solution**: Set `LARAVEL_MAIL_PREVIEW_ENABLED=true` in `.env`.

### Suppression not blocking sends
**Cause**: Suppression disabled or listener not registered.
**Solution**: Set `LARAVEL_MAIL_SUPPRESSION_ENABLED=true`. The `CheckSuppression` listener is only registered when suppression is enabled at boot time.
