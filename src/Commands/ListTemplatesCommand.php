<?php

namespace JeffersonGoncalves\LaravelMail\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

class ListTemplatesCommand extends Command
{
    protected $signature = 'mail:templates';

    protected $description = 'List all mail templates';

    public function handle(): int
    {
        $modelClass = config('laravel-mail.models.mail_template', MailTemplate::class);
        $templates = $modelClass::withCount('versions')->get();

        if ($templates->isEmpty()) {
            $this->components->info('No templates found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Name', 'Active', 'Locales', 'Versions', 'Last Updated'],
            $templates->map(fn ($t) => [
                $t->key,
                $t->name,
                $t->is_active ? 'Yes' : 'No',
                implode(', ', array_keys($t->getTranslations('subject'))),
                $t->versions_count,
                $t->updated_at?->format('Y-m-d H:i'),
            ])
        );

        return self::SUCCESS;
    }
}
