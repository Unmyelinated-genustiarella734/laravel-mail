<?php

namespace JeffersonGoncalves\LaravelMail\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

class PruneMailLogsCommand extends Command
{
    protected $signature = 'mail:prune {--days= : Number of days to keep}';

    protected $description = 'Delete mail logs older than the configured number of days';

    public function handle(): int
    {
        if (! config('laravel-mail.prune.enabled', true)) {
            $this->components->info('Mail log pruning is disabled.');

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?? config('laravel-mail.prune.older_than_days', 30));

        $cutoff = Carbon::now()->subDays($days);

        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        $count = $modelClass::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->components->info('No mail logs to prune.');

            return self::SUCCESS;
        }

        $modelClass::where('created_at', '<', $cutoff)->delete();

        $this->components->info("Pruned {$count} mail log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
