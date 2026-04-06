## Laravel Mail

Complete email management package: logging, database templates, delivery tracking, suppression, preview, statistics, notifications, and retry.

### Models

- **MailLog** — Logged email record (UUID PK, status enum, polymorphic `mailable`, json fields for addresses/headers/attachments/metadata)
- **MailTemplate** — Database email template (key unique, multi-locale subject/html_body/text_body as json arrays, variables json, layout, auto-versioning via observer)
- **MailTemplateVersion** — Automatic snapshot on content changes (version_number auto-incremented)
- **MailTrackingEvent** — Webhook delivery event (type enum: delivered/bounced/opened/clicked/complained/deferred, provider enum)
- **MailSuppression** — Blocked email address (reason enum: hard_bounce/complaint/manual)

### Email Logging

Enabled by default. All outgoing emails are captured via `MessageSent` event listener.

@verbatim
<code-snippet name="Logging is automatic" lang="php">
// Just send emails normally
Mail::to('user@example.com')->send(new WelcomeMail($user));
// A MailLog record is automatically created
</code-snippet>
@endverbatim

Config: `laravel-mail.logging.enabled`, `store_html_body`, `store_text_body`, `store_attachments`.

### Database Templates

Extend `TemplateMailable` for database-driven email content with Blade rendering and locale support.

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

### Delivery Tracking

Webhook handlers for SES, SendGrid, Postmark, Mailgun, and Resend. Routes: `POST /webhooks/mail/{provider}`.

Laravel events dispatched: `MailDelivered`, `MailBounced`, `MailComplained`, `MailOpened`, `MailClicked`, `MailDeferred`.

### Suppression List

When enabled, hard bounces and complaints auto-suppress addresses. Suppressed addresses are blocked from future sends.

Config: `laravel-mail.suppression.enabled`.

### Browser Preview

View emails in the browser via signed URLs. Access via `$mailLog->preview_url` or `$template->preview_url`.

Config: `laravel-mail.preview.enabled`, `signed_urls`.

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

- `mail:prune` — Delete old mail logs (default 30 days)
- `mail:unsuppress {email}` — Remove address from suppression list
- `mail:retry` — Retry failed/soft-bounced emails

### Conventions

- All models use UUID primary keys and are config-driven (overridable via `laravel-mail.models.*`)
- Table names are configurable via `laravel-mail.database.tables.*`
- Multi-tenant support via `laravel-mail.tenant.enabled` and `tenant_id` column
- Use `HasMailLogs` trait on models to associate with mail logs (polymorphic)
- Templates store content as locale-keyed arrays: `['en' => '...', 'pt_BR' => '...']`
