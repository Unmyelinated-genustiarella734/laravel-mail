<?php

namespace JeffersonGoncalves\LaravelMail\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

class MailStats
{
    public function sent(Carbon $from, Carbon $to): int
    {
        return $this->logQuery()
            ->where('status', '!=', MailStatus::Pending)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    public function delivered(Carbon $from, Carbon $to): int
    {
        return $this->countByStatus(MailStatus::Delivered, $from, $to);
    }

    public function bounced(Carbon $from, Carbon $to): int
    {
        return $this->countByStatus(MailStatus::Bounced, $from, $to);
    }

    public function complained(Carbon $from, Carbon $to): int
    {
        return $this->countByStatus(MailStatus::Complained, $from, $to);
    }

    public function opened(Carbon $from, Carbon $to): int
    {
        return $this->countByTrackingType(TrackingEventType::Opened, $from, $to);
    }

    public function clicked(Carbon $from, Carbon $to): int
    {
        return $this->countByTrackingType(TrackingEventType::Clicked, $from, $to);
    }

    public function deliveryRate(Carbon $from, Carbon $to): float
    {
        $sent = $this->sent($from, $to);

        if ($sent === 0) {
            return 0.0;
        }

        return round($this->delivered($from, $to) / $sent * 100, 2);
    }

    public function bounceRate(Carbon $from, Carbon $to): float
    {
        $sent = $this->sent($from, $to);

        if ($sent === 0) {
            return 0.0;
        }

        return round($this->bounced($from, $to) / $sent * 100, 2);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function dailyStats(Carbon $from, Carbon $to): Collection
    {
        $logModel = config('laravel-mail.models.mail_log', MailLog::class);
        $table = (new $logModel)->getTable();

        return DB::connection((new $logModel)->getConnectionName())
            ->table($table)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw("SUM(CASE WHEN status != 'pending' THEN 1 ELSE 0 END) as sent"),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced"),
                DB::raw("SUM(CASE WHEN status = 'complained' THEN 1 ELSE 0 END) as complained"),
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
    }

    protected function countByStatus(MailStatus $status, Carbon $from, Carbon $to): int
    {
        return $this->logQuery()
            ->where('status', $status)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    protected function countByTrackingType(TrackingEventType $type, Carbon $from, Carbon $to): int
    {
        $modelClass = config('laravel-mail.models.mail_tracking_event', MailTrackingEvent::class);

        return $modelClass::where('type', $type)
            ->whereBetween('created_at', [$from, $to])
            ->distinct('mail_log_id')
            ->count('mail_log_id');
    }

    /**
     * @return Builder<MailLog>
     */
    protected function logQuery(): Builder
    {
        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        return $modelClass::query();
    }
}
