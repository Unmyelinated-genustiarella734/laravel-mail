---
name: laravel-mail-development
description: Build and work with Laravel Mail features including email logging, database templates (spatie/laravel-translatable), webhook tracking with idempotency, pixel tracking (provider-independent open & click tracking), suppression lists, inline CSS, List-Unsubscribe headers, browser preview, statistics, notification channels, retry, attachment storage, prune policies, and CLI commands.
---

# Laravel Mail Development

## When to use this skill

Use this skill when:
- Setting up email logging and configuring what gets stored
- Creating database email templates with multi-locale support (spatie/laravel-translatable)
- Configuring webhook delivery tracking for email providers
- Setting up pixel tracking (provider-independent open & click tracking)
- Implementing suppression lists for bounced/complained addresses
- Setting up browser preview for sent emails and templates
- Querying email statistics and building dashboards
- Using the notification channel with database templates
- Configuring email retry for failed/bounced sends
- Associating mail logs with models via polymorphic relations
- Configuring inline CSS for email client compatibility
- Setting up List-Unsubscribe headers for Gmail/Yahoo compliance
- Storing email attachment files to disk
- Configuring per-status prune retention policies
- Using CLI commands (mail:send-test, mail:templates, mail:stats)

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
foreignUuid mail_template_id  -- auto-set when sent via TemplateMailable
string tenant_id
timestamps
```

### mail_templates
```
uuid id PK
string key (unique)
string name
string mailable_class
json subject, html_body, text_body  -- spatie/laravel-translatable (HasTranslations trait)
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
json subject, html_body, text_body  -- snapshots via getTranslations()
string change_note, author
unsignedInteger version_number
timestamp created_at
```

### mail_tracking_events
```
uuid id PK
foreignUuid mail_log_id (cascade delete)
string type (delivered|bounced|opened|clicked|complained|deferred)
string provider (ses|sendgrid|postmark|mailgun|resend|pixel)
string provider_event_id (nullable, indexed)  -- idempotency key
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

## Translation API (spatie/laravel-translatable)

```php
// MailTemplate uses HasTranslations trait
// Translatable fields: subject, html_body, text_body

// Get for current locale
$template->subject; // Returns string for app()->getLocale()

// Get for specific locale (with fallback)
$template->getSubjectForLocale('pt_BR');
$template->getHtmlBodyForLocale('en');
$template->getTextBodyForLocale('es');

// Get all translations as array
$template->getTranslations('subject'); // ['en' => '...', 'pt_BR' => '...']

// Set translation for a specific locale
$template->setTranslation('subject', 'fr', 'Bienvenue !');
$template->save();
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

TemplateMailable features:
- Auto-resolves template by key from database
- Renders Blade syntax in subject/body with provided data
- Applies layout wrapping (per-template or default from config)
- Inlines CSS automatically when `templates.inline_css` is enabled
- Injects `X-LaravelMail-TemplateID` header for auto-association with MailLog
- Adds `List-Unsubscribe` headers when `templates.unsubscribe.enabled` is true
- Supports `Queueable` (implements `ShouldQueue` pattern)
- Falls back to view-based content when template not found

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

Available events: `MailDelivered`, `MailBounced`, `MailComplained`, `MailOpened`, `MailClicked`, `MailDeferred`.

## Webhook Idempotency

Each webhook handler extracts a `provider_event_id`:
- **SES**: SNS `MessageId`
- **SendGrid**: `sg_event_id`
- **Postmark**: `{MessageID}-{RecordType}`
- **Mailgun**: `event-data.id`
- **Resend**: Svix `svix-id` header

Duplicate webhooks are detected via `firstOrCreate` on `provider_event_id`. When a duplicate is found, no tracking event is created, no status update, no Laravel event dispatched.

## Pixel Tracking (Provider-Independent)

Track opens and clicks without provider webhooks. Works with any mailer including plain SMTP.

```php
// config/laravel-mail.php
'tracking' => [
    'pixel' => [
        'open_tracking' => env('LARAVEL_MAIL_PIXEL_OPEN_TRACKING', false),
        'click_tracking' => env('LARAVEL_MAIL_PIXEL_CLICK_TRACKING', false),
        'route_prefix' => 'mail/t',
        'route_middleware' => [],
        'signing_key' => env('LARAVEL_MAIL_PIXEL_SIGNING_KEY'), // null = uses APP_KEY
    ],
],
```

### How It Works

1. `InjectTrackingPixel` listener on `MessageSending` modifies HTML body
2. Open tracking: Injects `<img>` 1x1 transparent GIF before `</body>`
3. Click tracking: Rewrites `<a href>` links through `/mail/t/click/{id}` endpoint
4. Pixel loads → `MailTrackingEvent` recorded with `provider=pixel`, `MailOpened` dispatched
5. Link clicked → `MailTrackingEvent` recorded, `MailClicked` dispatched, 302 redirect to original URL

### Key Classes

- `Services\PixelTracker` — Injects pixel, rewrites links, generates/verifies HMAC-signed URLs
- `Http\Controllers\TrackingController` — Serves GIF pixel, handles click redirects
- `Listeners\InjectTrackingPixel` — Listener on `MessageSending` that calls `PixelTracker`
- `Services\TrackingEventRecorder` — Shared service for recording events (used by both webhooks and pixel)

### Security

- HMAC-SHA256 signed URLs prevent forgery
- Click redirects validate URL scheme (blocks `javascript:`, `data:`, `vbscript:`)
- `mailto:`, `tel:`, `sms:`, `#anchor` links are never rewritten
- Pixel responses: `Cache-Control: no-store` prevents caching

### Routes

| Endpoint | Purpose |
|----------|---------|
| `GET /mail/t/pixel/{id}?sig=...` | Serve 1x1 GIF, record open |
| `GET /mail/t/click/{id}?url=...&sig=...` | Record click, redirect |

Coexists with webhook tracking — both register in `mail_tracking_events` with different `provider` values.

## Previewing Templates

```php
use JeffersonGoncalves\LaravelMail\Actions\PreviewTemplateAction;

$action = new PreviewTemplateAction();
$preview = $action->execute($template, ['name' => 'Alice'], 'en');
// Returns: ['subject' => 'rendered subject', 'html' => 'rendered html (CSS inlined)', 'text' => null]
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

## CLI Commands

| Command | Description |
|---------|-------------|
| `mail:prune` | Delete old mail logs (`--days`, or uses per-status policies) |
| `mail:retry` | Retry failed/bounced emails (`--status`, `--hours`, `--limit`) |
| `mail:unsuppress {email}` | Remove address from suppression list |
| `mail:send-test {key} {email}` | Send test email using template (`--locale`, `--data`) |
| `mail:templates` | List all templates in a table |
| `mail:stats` | Show email statistics (`--days`, default 7) |

### Send Test Email via CLI

```bash
# Uses example data from template variables
php artisan mail:send-test welcome user@example.com

# With specific locale
php artisan mail:send-test welcome user@example.com --locale=pt_BR

# With custom JSON data
php artisan mail:send-test welcome user@example.com --data='{"name":"Alice"}'
```

## Configuration Reference

| Key | Default | Description |
|-----|---------|-------------|
| `logging.enabled` | `true` | Enable email logging |
| `logging.store_html_body` | `true` | Store HTML body |
| `logging.store_text_body` | `true` | Store text body |
| `logging.store_attachments` | `true` | Store attachment metadata |
| `logging.store_attachment_files` | `false` | Store actual attachment files to disk |
| `logging.attachments_disk` | `'local'` | Filesystem disk for attachments |
| `logging.attachments_path` | `'mail-attachments'` | Path prefix for stored attachments |
| `prune.enabled` | `true` | Enable pruning |
| `prune.older_than_days` | `30` | Default days to keep logs |
| `prune.policies` | `null` | Per-status retention: `['delivered' => 30, 'bounced' => 90]` |
| `tracking.enabled` | `false` | Enable webhook tracking |
| `tracking.pixel.open_tracking` | `false` | Inject tracking pixel for opens |
| `tracking.pixel.click_tracking` | `false` | Rewrite links for click tracking |
| `tracking.pixel.route_prefix` | `'mail/t'` | URL prefix for pixel routes |
| `tracking.pixel.signing_key` | `null` | HMAC key (null = APP_KEY) |
| `suppression.enabled` | `false` | Enable suppression list |
| `suppression.auto_suppress_hard_bounces` | `true` | Auto-suppress hard bounces |
| `suppression.auto_suppress_complaints` | `true` | Auto-suppress complaints |
| `retry.enabled` | `false` | Enable retry |
| `retry.max_attempts` | `3` | Max retry attempts |
| `preview.enabled` | `false` | Enable browser preview |
| `preview.signed_urls` | `true` | Require signed URLs |
| `templates.enabled` | `true` | Enable database templates |
| `templates.inline_css` | `true` | Inline CSS in template HTML |
| `templates.unsubscribe.enabled` | `false` | Add List-Unsubscribe headers |
| `templates.unsubscribe.url` | `null` | Unsubscribe URL (`{email}` placeholder) |
| `templates.unsubscribe.mailto` | `null` | Unsubscribe mailto address |
| `tenant.enabled` | `false` | Enable multi-tenancy |

## Troubleshooting

### Emails not being logged
**Cause**: Logging disabled or listeners not registered.
**Solution**: Check `LARAVEL_MAIL_LOGGING_ENABLED=true` in `.env`. Ensure the service provider is auto-discovered.

### mail_template_id not set on MailLog
**Cause**: Email was not sent via `TemplateMailable`.
**Solution**: Only emails sent through classes extending `TemplateMailable` get the template ID auto-linked via the `X-LaravelMail-TemplateID` header.

### Webhook returning 503
**Cause**: Tracking globally disabled.
**Solution**: Set `LARAVEL_MAIL_TRACKING_ENABLED=true` and enable the specific provider in config.

### Duplicate tracking events
**Cause**: Should not happen with idempotency. If it does, check that `provider_event_id` is being extracted.
**Solution**: Each handler extracts a unique ID from the payload. If the provider doesn't send one, fallback uses `mail_log_id + type + provider + occurred_at`.

### Template not rendering variables
**Cause**: Template uses Blade syntax but data not passed.
**Solution**: Ensure `templateData()` returns all variables used in the template body. Check the template is `is_active`.

### CSS not being inlined
**Cause**: Inline CSS disabled in config.
**Solution**: Set `LARAVEL_MAIL_INLINE_CSS=true` in `.env` or `templates.inline_css => true` in config.

### Preview URL returns 404
**Cause**: Preview feature disabled.
**Solution**: Set `LARAVEL_MAIL_PREVIEW_ENABLED=true` in `.env`.

### Suppression not blocking sends
**Cause**: Suppression disabled or listener not registered.
**Solution**: Set `LARAVEL_MAIL_SUPPRESSION_ENABLED=true`. The `CheckSuppression` listener is only registered when suppression is enabled at boot time.

### Attachment files not stored
**Cause**: File storage disabled.
**Solution**: Set `LARAVEL_MAIL_STORE_ATTACHMENT_FILES=true`. Configure `attachments_disk` for your preferred storage.
