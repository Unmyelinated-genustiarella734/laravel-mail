<?php

namespace JeffersonGoncalves\LaravelMail\Observers;

use JeffersonGoncalves\LaravelMail\Models\MailTemplate;
use JeffersonGoncalves\LaravelMail\Models\MailTemplateVersion;

class MailTemplateObserver
{
    public function created(MailTemplate $template): void
    {
        $this->createVersion($template, 'Initial version');
    }

    public function updated(MailTemplate $template): void
    {
        $dirty = $template->getDirty();

        $contentFields = ['subject', 'html_body', 'text_body'];
        $hasContentChanges = ! empty(array_intersect(array_keys($dirty), $contentFields));

        if (! $hasContentChanges) {
            return;
        }

        $this->createVersion($template);
    }

    protected function createVersion(MailTemplate $template, ?string $changeNote = null): void
    {
        $versionModelClass = config(
            'laravel-mail.models.mail_template_version',
            MailTemplateVersion::class,
        );

        $latestVersion = $versionModelClass::where('mail_template_id', $template->id)
            ->max('version_number') ?? 0;

        $versionModelClass::create([
            'mail_template_id' => $template->id,
            'subject' => $template->subject,
            'html_body' => $template->html_body,
            'text_body' => $template->text_body,
            'change_note' => $changeNote,
            'version_number' => $latestVersion + 1,
            'created_at' => now(),
        ]);
    }
}
