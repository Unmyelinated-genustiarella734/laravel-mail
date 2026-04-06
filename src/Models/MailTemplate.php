<?php

namespace JeffersonGoncalves\LaravelMail\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use JeffersonGoncalves\LaravelMail\Observers\MailTemplateObserver;

/**
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string|null $mailable_class
 * @property array<string, string> $subject
 * @property array<string, string> $html_body
 * @property array<string, string>|null $text_body
 * @property array<int, array{name: string, type: string, example: string}>|null $variables
 * @property string|null $layout
 * @property string|null $tenant_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $preview_url
 * @property-read Collection<int, MailTemplateVersion> $versions
 * @property-read Collection<int, MailLog> $logs
 */
#[ObservedBy(MailTemplateObserver::class)]
class MailTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'name',
        'mailable_class',
        'subject',
        'html_body',
        'text_body',
        'variables',
        'layout',
        'tenant_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'subject' => 'array',
            'html_body' => 'array',
            'text_body' => 'array',
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('laravel-mail.database.tables.mail_templates', parent::getTable());
    }

    public function getConnectionName(): ?string
    {
        return config('laravel-mail.database.connection') ?? parent::getConnectionName();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(
            config('laravel-mail.models.mail_template_version', MailTemplateVersion::class),
            'mail_template_id'
        );
    }

    public function logs(): HasMany
    {
        return $this->hasMany(
            config('laravel-mail.models.mail_log', MailLog::class),
            'mail_template_id'
        );
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if (! config('laravel-mail.preview.enabled', false)) {
            return null;
        }

        if (config('laravel-mail.preview.signed_urls', true)) {
            return URL::signedRoute('laravel-mail.preview.template', ['mailTemplate' => $this->id]);
        }

        return route('laravel-mail.preview.template', ['mailTemplate' => $this->id]);
    }

    public function getSubjectForLocale(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $this->subject[$locale] ?? $this->subject[array_key_first($this->subject)] ?? '';
    }

    public function getHtmlBodyForLocale(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $this->html_body[$locale] ?? $this->html_body[array_key_first($this->html_body)] ?? '';
    }

    public function getTextBodyForLocale(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $this->text_body[$locale] ?? $this->text_body[array_key_first($this->text_body)] ?? '';
    }
}
