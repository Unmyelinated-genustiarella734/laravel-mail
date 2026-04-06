<?php

namespace JeffersonGoncalves\LaravelMail\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\SuppressionReason;

/**
 * @property string $id
 * @property string $email
 * @property SuppressionReason $reason
 * @property string|null $provider
 * @property string|null $mail_log_id
 * @property Carbon|null $suppressed_at
 * @property string|null $tenant_id
 * @property-read MailLog|null $mailLog
 */
class MailSuppression extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'email',
        'reason',
        'provider',
        'mail_log_id',
        'suppressed_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'reason' => SuppressionReason::class,
            'suppressed_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('laravel-mail.database.tables.mail_suppressions', parent::getTable());
    }

    public function getConnectionName(): ?string
    {
        return config('laravel-mail.database.connection') ?? parent::getConnectionName();
    }

    public function mailLog(): BelongsTo
    {
        return $this->belongsTo(
            config('laravel-mail.models.mail_log', MailLog::class),
            'mail_log_id'
        );
    }
}
