<?php

namespace JeffersonGoncalves\LaravelMail\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Mail\TemplateNotificationMailable;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

class SendTestMailCommand extends Command
{
    protected $signature = 'mail:send-test {key : The template key} {email : Recipient email address} {--locale= : Locale for the template} {--data= : JSON-encoded data for template variables}';

    protected $description = 'Send a test email using a mail template';

    public function handle(): int
    {
        $key = $this->argument('key');
        $email = $this->argument('email');
        $locale = $this->option('locale');

        /** @var string|null $dataOption */
        $dataOption = $this->option('data');
        $data = json_decode($dataOption ?? '{}', true) ?: [];

        $modelClass = config('laravel-mail.models.mail_template', MailTemplate::class);
        $template = $modelClass::where('key', $key)->where('is_active', true)->first();

        if (! $template) {
            $this->components->error("Template with key '{$key}' not found or inactive.");

            return self::FAILURE;
        }

        if ($template->variables) {
            foreach ($template->variables as $variable) {
                $data[$variable['name']] ??= $variable['example'];
            }
        }

        if ($locale) {
            app()->setLocale($locale);
        }

        $mailable = new TemplateNotificationMailable($key, $data);

        Mail::to($email)->send($mailable);

        $this->components->info("Test email sent to {$email} using template '{$key}'.");

        return self::SUCCESS;
    }
}
