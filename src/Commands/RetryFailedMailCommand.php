<?php

namespace JeffersonGoncalves\LaravelMail\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use JeffersonGoncalves\LaravelMail\Actions\RetryFailedMailAction;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

class RetryFailedMailCommand extends Command
{
    protected $signature = 'mail:retry {--status=failed : Status to retry (failed or bounced)} {--hours=24 : Only retry emails from the last N hours} {--limit=100 : Maximum number of emails to retry}';

    protected $description = 'Retry sending failed or soft-bounced emails';

    public function handle(): int
    {
        if (! config('laravel-mail.retry.enabled', false)) {
            $this->components->info('Mail retry is disabled.');

            return self::SUCCESS;
        }

        $status = $this->option('status');
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');

        $mailStatus = match ($status) {
            'failed' => MailStatus::Failed,
            'bounced' => MailStatus::Bounced,
            default => MailStatus::Failed,
        };

        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);
        $cutoff = Carbon::now()->subHours($hours);

        $query = $modelClass::where('status', $mailStatus)
            ->where('created_at', '>=', $cutoff)
            ->limit($limit);

        $logs = $query->get();

        if ($logs->isEmpty()) {
            $this->components->info('No emails to retry.');

            return self::SUCCESS;
        }

        $action = new RetryFailedMailAction;
        $retried = 0;
        $skipped = 0;

        foreach ($logs as $log) {
            if ($mailStatus === MailStatus::Bounced) {
                $latestBounce = $log->trackingEvents()
                    ->where('type', 'bounced')
                    ->latest('created_at')
                    ->first();

                if ($latestBounce && (
                    str_starts_with($latestBounce->bounce_type ?? '', 'Permanent') ||
                    str_starts_with($latestBounce->bounce_type ?? '', 'permanent') ||
                    ($latestBounce->bounce_type ?? '') === 'hard'
                )) {
                    $skipped++;

                    continue;
                }
            }

            if ($action->execute($log)) {
                $retried++;
            } else {
                $skipped++;
            }
        }

        $this->components->info("Retried {$retried} email(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
