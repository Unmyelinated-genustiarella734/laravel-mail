<?php

namespace JeffersonGoncalves\LaravelMail\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Services\MailStats;

class MailStatsCommand extends Command
{
    protected $signature = 'mail:stats {--days=7 : Number of days to show stats for}';

    protected $description = 'Show email statistics';

    public function handle(MailStats $stats): int
    {
        $days = (int) $this->option('days');
        $from = Carbon::now()->subDays($days);
        $to = Carbon::now();

        $sent = $stats->sent($from, $to);
        $delivered = $stats->delivered($from, $to);
        $bounced = $stats->bounced($from, $to);
        $complained = $stats->complained($from, $to);
        $opened = $stats->opened($from, $to);
        $clicked = $stats->clicked($from, $to);

        $this->components->info("Mail statistics for the last {$days} days:");

        $this->table(
            ['Metric', 'Count', 'Rate'],
            [
                ['Sent', $sent, '-'],
                ['Delivered', $delivered, $stats->deliveryRate($from, $to).'%'],
                ['Bounced', $bounced, $stats->bounceRate($from, $to).'%'],
                ['Complained', $complained, $sent > 0 ? round($complained / $sent * 100, 2).'%' : '0%'],
                ['Opened', $opened, $sent > 0 ? round($opened / $sent * 100, 2).'%' : '0%'],
                ['Clicked', $clicked, $sent > 0 ? round($clicked / $sent * 100, 2).'%' : '0%'],
            ]
        );

        return self::SUCCESS;
    }
}
