<?php

namespace JeffersonGoncalves\LaravelMail\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
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

        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        /** @var array<string, int>|null $policies */
        $policies = config('laravel-mail.prune.policies');

        if (! empty($policies) && ! $this->option('days')) {
            return $this->pruneByPolicies($modelClass, $policies);
        }

        $days = (int) ($this->option('days') ?? config('laravel-mail.prune.older_than_days', 30));
        $cutoff = Carbon::now()->subDays($days);

        $count = $modelClass::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->components->info('No mail logs to prune.');

            return self::SUCCESS;
        }

        $this->deleteLogsWithAttachments($modelClass, $modelClass::where('created_at', '<', $cutoff));

        $this->components->info("Pruned {$count} mail log(s) older than {$days} days.");

        return self::SUCCESS;
    }

    /**
     * @param  class-string<MailLog>  $modelClass
     * @param  array<string, int>  $policies
     */
    protected function pruneByPolicies(string $modelClass, array $policies): int
    {
        $totalPruned = 0;

        foreach ($policies as $status => $retentionDays) {
            $cutoff = Carbon::now()->subDays($retentionDays);

            $query = $modelClass::where('status', $status)
                ->where('created_at', '<', $cutoff);

            $count = $query->count();

            if ($count > 0) {
                $this->deleteLogsWithAttachments($modelClass, $modelClass::where('status', $status)->where('created_at', '<', $cutoff));
                $totalPruned += $count;
                $this->components->info("Pruned {$count} '{$status}' mail log(s) older than {$retentionDays} days.");
            }
        }

        if ($totalPruned === 0) {
            $this->components->info('No mail logs to prune.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string<MailLog>  $modelClass
     * @param  Builder<MailLog>  $query
     */
    protected function deleteLogsWithAttachments(string $modelClass, $query): void
    {
        if (config('laravel-mail.logging.store_attachment_files', false)) {
            $query->clone()->chunk(100, function ($logs) {
                foreach ($logs as $log) {
                    $this->cleanupAttachmentFiles($log);
                }
            });
        }

        $query->delete();
    }

    protected function cleanupAttachmentFiles(MailLog $log): void
    {
        if (! $log->attachments) {
            return;
        }

        foreach ($log->attachments as $attachment) {
            if (isset($attachment['path'], $attachment['disk'])) {
                Storage::disk($attachment['disk'])->delete($attachment['path']);
            }
        }
    }
}
