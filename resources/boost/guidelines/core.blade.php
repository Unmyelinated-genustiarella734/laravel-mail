## Laravel Mail

Complete email management package: logging, database templates (spatie/laravel-translatable), delivery tracking, webhook idempotency, pixel tracking (provider-independent open & click), suppression, inline CSS, List-Unsubscribe headers, preview, statistics, notifications, retry, attachment storage, and CLI commands.

### Models

- **MailLog** — Logged email record (UUID PK, status enum, polymorphic `mailable`, json fields for addresses/headers/attachments/metadata, `mail_template_id` auto-linked)
- **MailTemplate** — Database email template (key unique, `HasTranslations` via spatie/laravel-translatable for subject/html_body/text_body, variables json, layout, auto-versioning via observer)
- **MailTemplateVersion** — Automatic snapshot on content changes (version_number auto-incremented)
- **MailTrackingEvent** — Delivery/tracking event (type enum: delivered/bounced/opened/clicked/complained/deferred, provider enum: ses/sendgrid/postmark/mailgun/resend/pixel, `provider_event_id` for idempotency)
- **MailSuppression** — Blocked email address (reason enum: hard_bounce/complaint/manual)

### Email Logging

Enabled by default. All outgoing emails are captured via `MessageSent` event listener. When using `TemplateMailable`, the `mail_template_id` is automatically set on the log.

@verbatim
<code-snippet name="Logging is automatic" lang="php">
// Just send emails normally
Mail::to('user@example.com')->send(new WelcomeMail($user));
// A MailLog record is automatically created
</code-snippet>
@endverbatim

Config: `laravel-mail.logging.enabled`, `store_html_body`, `store_text_body`, `store_attachments`, `store_attachment_files`, `attachments_disk`, `attachments_path`.

### Database Templates

Extend `TemplateMailable` for database-driven email content with Blade rendering and locale support via `spatie/laravel-translatable`.

@verbatim
<code-snippet name="TemplateMailable usage" lang="php">
use JeffersonGoncalves\LaravelMail\Mail\TemplateMailable;

class WelcomeEmail extends TemplateMailable
{
    public function __construct(public User $user) {}

    public function templateKey(): string { return 'welcome'; }

    public function templateData(): array { return ['name' => $this->user->name]; }
}
</code-snippet>
@endverbatim

Template translations use `spatie/laravel-translatable`:

@verbatim
<code-snippet name="Translation API" lang="php">
$template->getSubjectForLocale('pt_BR');
$template->getTranslations('subject'); // ['en' => '...', 'pt_BR' => '...']
$template->setTranslation('subject', 'fr', 'Bienvenue !');
</code-snippet>
@endverbatim

### Inline CSS

Automatic CSS inlining for email client compatibility. Enabled by default via `laravel-mail.templates.inline_css`. Uses `tijsverkoyen/css-to-inline-styles` (included with Laravel). Applied on both `TemplateMailable` and `PreviewTemplateAction`.

### List-Unsubscribe Headers

Gmail/Yahoo compliance headers. When enabled, `TemplateMailable` adds `List-Unsubscribe` and `List-Unsubscribe-Post` headers. Supports `{email}` placeholder for recipient substitution.

Config: `laravel-mail.templates.unsubscribe.enabled`, `.url`, `.mailto`.

### Delivery Tracking

Webhook handlers for SES, SendGrid, Postmark, Mailgun, and Resend. Routes: `POST /webhooks/mail/{provider}`.

**Idempotency:** Each handler extracts a `provider_event_id` from the payload. Duplicate webhooks are detected via `firstOrCreate` and ignored — no duplicate events, no duplicate status updates.

Laravel events dispatched: `MailDelivered`, `MailBounced`, `MailComplained`, `MailOpened`, `MailClicked`, `MailDeferred`.

### Pixel Tracking (Provider-Independent)

Track opens and clicks without provider webhooks. Works with any mailer including plain SMTP. Injects a 1x1 transparent GIF pixel for open tracking and rewrites links for click tracking.

Routes: `GET /mail/t/pixel/{id}?sig=...` (open), `GET /mail/t/click/{id}?url=...&sig=...` (click). URLs are HMAC-SHA256 signed.

Key classes: `Services\PixelTracker`, `Http\Controllers\TrackingController`, `Listeners\InjectTrackingPixel`, `Services\TrackingEventRecorder`.

Config: `laravel-mail.tracking.pixel.open_tracking`, `click_tracking`, `route_prefix`, `signing_key`.

Coexists with webhook tracking — events registered with `provider=pixel` in `mail_tracking_events`.

### Suppression List

When enabled, hard bounces and complaints auto-suppress addresses. Suppressed addresses are blocked from future sends.

Config: `laravel-mail.suppression.enabled`.

### Browser Preview

View emails in the browser via signed URLs. Access via `$mailLog->preview_url` or `$template->preview_url`.

Config: `laravel-mail.preview.enabled`, `signed_urls`.

### Attachment File Storage

Optionally store email attachment files to disk. When enabled, each attachment gets `path` and `disk` in its metadata. Files are automatically cleaned up on prune.

Config: `laravel-mail.logging.store_attachment_files`, `attachments_disk`, `attachments_path`.

### Statistics

@verbatim
<code-snippet name="MailStats facade" lang="php">
use JeffersonGoncalves\LaravelMail\Facades\MailStats;

MailStats::sent($from, $to);
MailStats::delivered($from, $to);
MailStats::deliveryRate($from, $to);
MailStats::dailyStats($from, $to);
</code-snippet>
@endverbatim

### Notification Channel

@verbatim
<code-snippet name="TemplateMailChannel in Notification" lang="php">
use JeffersonGoncalves\LaravelMail\Channels\TemplateMailChannel;

public function via($notifiable): array { return [TemplateMailChannel::class]; }

public function toTemplateMail($notifiable): array
{
    return ['template_key' => 'welcome', 'data' => ['name' => $notifiable->name]];
}
</code-snippet>
@endverbatim

### Artisan Commands

- `mail:prune` — Delete old mail logs (supports `--days` and per-status policies)
- `mail:unsuppress {email}` — Remove address from suppression list
- `mail:retry` — Retry failed/soft-bounced emails (supports `--status`, `--hours`, `--limit`)
- `mail:send-test {key} {email}` — Send test email using a template (supports `--locale`, `--data`)
- `mail:templates` — List all templates in a table
- `mail:stats` — Show email statistics (supports `--days`)

### Pruning Policies

Default: delete all logs older than 30 days. Optionally configure per-status retention:

@verbatim
<code-snippet name="Per-status prune policies" lang="php">
// config/laravel-mail.php
'prune' => [
    'policies' => [
        'delivered' => 30,
        'bounced' => 90,
        'complained' => 365,
    ],
],
</code-snippet>
@endverbatim

### Conventions

- All models use UUID primary keys and are config-driven (overridable via `laravel-mail.models.*`)
- Table names are configurable via `laravel-mail.database.tables.*`
- Multi-tenant support via `laravel-mail.tenant.enabled` and `tenant_id` column
- Use `HasMailLogs` trait on models to associate with mail logs (polymorphic)
- Templates use `spatie/laravel-translatable` `HasTranslations` trait for multi-locale content
- TemplateMailable auto-links `mail_template_id` on the MailLog via `X-LaravelMail-TemplateID` header
