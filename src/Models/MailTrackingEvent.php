<?php

namespace JeffersonGoncalves\LaravelMail\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;

/**
 * @property string $id
 * @property string $mail_log_id
 * @property TrackingEventType $type
 * @property TrackingProvider $provider
 * @property array<string, mixed>|null $payload
 * @property string|null $recipient
 * @property string|null $url
 * @property string|null $bounce_type
 * @property Carbon|null $occurred_at
 * @property Carbon|null $created_at
 * @property-read MailLog $mailLog
 */
class MailTrackingEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'mail_log_id',
        'type',
        'provider',
        'payload',
        'recipient',
        'url',
        'bounce_type',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TrackingEventType::class,
            'provider' => TrackingProvider::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('laravel-mail.database.tables.mail_tracking_events', parent::getTable());
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
