# Changelog

All notable changes to `laravel-mail` will be documented in this file.

## 1.2.0 - 2026-04-05

### What's New

#### New Features

- **Webhook Idempotency** — Duplicate webhook deliveries are detected and ignored via `provider_event_id`
- **MailLog Template ID Association** — Emails sent via `TemplateMailable` automatically link to the template
- **Inline CSS** — Automatic CSS inlining for email client compatibility (Outlook, Gmail, Yahoo)
- **List-Unsubscribe Headers** — Gmail/Yahoo compliance with `List-Unsubscribe` and `List-Unsubscribe-Post`
- **Attachment File Storage** — Optionally store email attachment files to disk (S3, local, etc.)
- **Per-Status Prune Policies** — Different retention periods per status (e.g. delivered=30d, bounced=90d)
- **`mail:send-test` Command** — Send test emails using templates via CLI
- **`mail:templates` Command** — List all templates in a table
- **`mail:stats` Command** — Show email statistics in the terminal
- **spatie/laravel-translatable Integration** — `MailTemplate` now uses `HasTranslations` trait

#### Bug Fixes

- Removed empty `VerifyWebhookSignature` middleware (validation already in handlers)
- Webhook/preview controllers return proper 503/404 when features are disabled

#### Tests

- 148 tests, 346 assertions — all passing
- PHPStan level 5: 0 errors
- New test files for all new features

## 1.1.0 - 2026-04-05

### What's New

#### New Features

- **Tracking Events** — Laravel events dispatched on webhook processing: `MailDelivered`, `MailBounced`, `MailComplained`, `MailOpened`, `MailClicked`, `MailDeferred`. Listen to react to delivery status changes (disable accounts on bounce, alert on complaints, etc.)
  
- **Suppression List** — Automatically suppress email addresses that hard bounce or receive spam complaints. Suppressed addresses are blocked from future sends. Includes `mail:unsuppress` command for manual management.
  
- **Statistics** — `MailStats` facade with query helpers: `sent()`, `delivered()`, `bounced()`, `complained()`, `opened()`, `clicked()`, `deliveryRate()`, `bounceRate()`, `dailyStats()`.
  
- **Template Preview Action** — Render templates with example data without sending: `PreviewTemplateAction::execute($template, $data, $locale)`.
  
- **Browser Preview** — View sent emails and templates in the browser via signed URLs. Access via `$mailLog->preview_url` and `$template->preview_url`.
  
- **Notification Channel** — `TemplateMailChannel` + `TemplateNotificationMailable` to send database templates via Laravel Notifications.
  
- **Retry Failed Emails** — `RetryFailedMailAction` with max attempts + `mail:retry` command. Hard bounces are automatically skipped.
  
- **Laravel Boost Integration** — `resources/boost/` with guidelines and development skill for AI-assisted development.
  

#### Changes

- Pinned Pest 3 for L11/L12 and Pest 4 for L13 in CI matrix

#### Stats

- 118 tests, 281 assertions
- PHPStan level 5, zero errors
- Laravel 11.x / 12.x / 13.x support

## 1.0.0 - 2026-04-05

### What's New

Initial release of Laravel Mail — complete email management for Laravel.

#### Features

- **Email Logging** — Automatic logging of all outgoing emails via `MessageSent` event
- **Database Templates** — Store email templates with multi-locale translation and Blade rendering
- **Template Versioning** — Automatic version history on every content change
- **TemplateMailable** — Base mailable class that fetches templates from the database with fallback support
- **Delivery Tracking** — Webhook handlers for 5 providers (SES, SendGrid, Postmark, Mailgun, Resend) with HMAC/signature validation
- **Resend Emails** — Resend any previously sent email from the log
- **Pruning** — `mail:prune` artisan command with configurable retention
- **HasMailLogs Trait** — Polymorphic association for any model
- **Multi-Tenant** — Optional tenant scoping for all tables
- **Customizable** — Override models, table names, and database connection

#### Requirements

- PHP 8.2+
- Laravel 11.x / 12.x / 13.x

## v1.0.0 - 2026-04-05

### What's New

Initial release of Laravel Mail — complete email management for Laravel.

#### Features

- **Email Logging** — Automatic logging of all outgoing emails via `MessageSent` event
- **Database Templates** — Store email templates with multi-locale translation and Blade rendering
- **Template Versioning** — Automatic version history on every content change
- **TemplateMailable** — Base mailable class that fetches templates from the database with fallback support
- **Delivery Tracking** — Webhook handlers for 5 providers (SES, SendGrid, Postmark, Mailgun, Resend) with HMAC/signature validation
- **Resend Emails** — Resend any previously sent email from the log
- **Pruning** — `mail:prune` artisan command with configurable retention
- **HasMailLogs Trait** — Polymorphic association for any model
- **Multi-Tenant** — Optional tenant scoping for all tables
- **Customizable** — Override models, table names, and database connection

#### Requirements

- PHP 8.2+
- Laravel 11.x / 12.x / 13.x

## Unreleased
