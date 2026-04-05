<?php

namespace JeffersonGoncalves\LaravelMail\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

abstract class TemplateMailable extends Mailable
{
    use Queueable, SerializesModels;

    protected ?MailTemplate $mailTemplate = null;

    abstract public function templateKey(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function templateData(): array;

    public function envelope(): Envelope
    {
        $template = $this->resolveTemplate();

        return new Envelope(
            subject: $template
                ? $this->renderBlade($template->getSubjectForLocale(), $this->templateData())
                : $this->fallbackSubject(),
        );
    }

    public function content(): Content
    {
        $template = $this->resolveTemplate();

        if (! $template) {
            return $this->fallbackContent();
        }

        $data = $this->templateData();
        $htmlBody = $this->renderBlade($template->getHtmlBodyForLocale(), $data);
        $layout = $template->layout ?? config('laravel-mail.templates.default_layout');

        if ($layout) {
            $htmlBody = $this->wrapInLayout($layout, $htmlBody, $data);
        }

        $this->html($htmlBody);

        $textBody = $template->getTextBodyForLocale();
        if ($textBody) {
            $this->text(new HtmlString($this->renderBlade($textBody, $data)));
        }

        return new Content;
    }

    public function resolveTemplate(): ?MailTemplate
    {
        if ($this->mailTemplate !== null) {
            return $this->mailTemplate;
        }

        if (! config('laravel-mail.templates.enabled', true)) {
            return null;
        }

        $modelClass = config('laravel-mail.models.mail_template', MailTemplate::class);

        $this->mailTemplate = $modelClass::where('key', $this->templateKey())
            ->where('is_active', true)
            ->first();

        return $this->mailTemplate;
    }

    public function useTemplate(MailTemplate $template): static
    {
        $this->mailTemplate = $template;

        return $this;
    }

    protected function fallbackSubject(): string
    {
        return '';
    }

    protected function fallbackContent(): Content
    {
        return new Content;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderBlade(string $template, array $data): string
    {
        return Blade::render($template, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function wrapInLayout(string $layout, string $body, array $data): string
    {
        return Blade::render(
            $layout,
            array_merge($data, ['slot' => $body]),
        );
    }

    public function getMailTemplate(): ?MailTemplate
    {
        return $this->mailTemplate;
    }
}
