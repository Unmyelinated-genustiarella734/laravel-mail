<?php

namespace JeffersonGoncalves\LaravelMail\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $mail_template_id
 * @property array<string, string> $subject
 * @property array<string, string> $html_body
 * @property array<string, string>|null $text_body
 * @property string|null $change_note
 * @property string|null $author
 * @property int $version_number
 * @property Carbon|null $created_at
 * @property-read MailTemplate $template
 */
class MailTemplateVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'mail_template_id',
        'subject',
        'html_body',
        'text_body',
        'change_note',
        'author',
        'version_number',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'subject' => 'array',
            'html_body' => 'array',
            'text_body' => 'array',
            'version_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('laravel-mail.database.tables.mail_template_versions', parent::getTable());
    }

    public function getConnectionName(): ?string
    {
        return config('laravel-mail.database.connection') ?? parent::getConnectionName();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            config('laravel-mail.models.mail_template', MailTemplate::class),
            'mail_template_id'
        );
    }
}
