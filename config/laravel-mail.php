<?php

use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;
use JeffersonGoncalves\LaravelMail\Models\MailTemplateVersion;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Mail Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, all outgoing emails will be logged to the database.
    | You can disable this in specific environments if needed.
    |
    */

    'logging' => [
        'enabled' => env('LARAVEL_MAIL_LOGGING_ENABLED', true),

        /*
        | Store the HTML body of the email in the database.
        | Disable to save storage space.
        */
        'store_html_body' => env('LARAVEL_MAIL_STORE_HTML', true),

        /*
        | Store the plain text body of the email in the database.
        */
        'store_text_body' => env('LARAVEL_MAIL_STORE_TEXT', true),

        /*
        | Store attachment metadata (name, size, content-type).
        | The actual attachment content is NOT stored.
        */
        'store_attachments' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Automatically delete old mail logs after the specified number of days.
    | Set to null to disable pruning.
    |
    */

    'prune' => [
        'enabled' => true,
        'older_than_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Tracking
    |--------------------------------------------------------------------------
    |
    | Configure webhook endpoints for delivery tracking from email providers.
    | Each provider requires its own signing secret for HMAC validation.
    |
    */

    'tracking' => [
        'enabled' => env('LARAVEL_MAIL_TRACKING_ENABLED', false),

        'route_prefix' => 'webhooks/mail',

        'route_middleware' => [],

        'providers' => [
            'ses' => [
                'enabled' => false,
            ],
            'sendgrid' => [
                'enabled' => false,
                'signing_secret' => env('LARAVEL_MAIL_SENDGRID_SIGNING_SECRET'),
            ],
            'postmark' => [
                'enabled' => false,
                'username' => env('LARAVEL_MAIL_POSTMARK_WEBHOOK_USERNAME'),
                'password' => env('LARAVEL_MAIL_POSTMARK_WEBHOOK_PASSWORD'),
            ],
            'mailgun' => [
                'enabled' => false,
                'signing_key' => env('LARAVEL_MAIL_MAILGUN_SIGNING_KEY'),
            ],
            'resend' => [
                'enabled' => false,
                'signing_secret' => env('LARAVEL_MAIL_RESEND_SIGNING_SECRET'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    | Configuration for database-backed email templates.
    |
    */

    'templates' => [
        'enabled' => true,

        /*
        | Default layout to wrap template content.
        | Set to null for no layout wrapping.
        */
        'default_layout' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | Enable tenant scoping for mail logs and templates.
    | When enabled, you must provide a tenant resolver callback.
    |
    */

    'tenant' => [
        'enabled' => false,
        'column' => 'tenant_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Customize the database connection and table names used by the package.
    |
    */

    'database' => [
        'connection' => null,

        'tables' => [
            'mail_logs' => 'mail_logs',
            'mail_templates' => 'mail_templates',
            'mail_template_versions' => 'mail_template_versions',
            'mail_tracking_events' => 'mail_tracking_events',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Classes
    |--------------------------------------------------------------------------
    |
    | You can override the default models used by the package.
    |
    */

    'models' => [
        'mail_log' => MailLog::class,
        'mail_template' => MailTemplate::class,
        'mail_template_version' => MailTemplateVersion::class,
        'mail_tracking_event' => MailTrackingEvent::class,
    ],

];
