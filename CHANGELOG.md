# Changelog

All notable changes to `laravel-mail` will be documented in this file.

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
