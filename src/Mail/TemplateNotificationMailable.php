<?php

namespace JeffersonGoncalves\LaravelMail\Mail;

use Illuminate\Mail\Mailables\Content;

class TemplateNotificationMailable extends TemplateMailable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        protected string $key,
        protected array $data = [],
    ) {}

    public function templateKey(): string
    {
        return $this->key;
    }

    public function templateData(): array
    {
        return $this->data;
    }

    protected function fallbackSubject(): string
    {
        return '';
    }

    protected function fallbackContent(): Content
    {
        return new Content;
    }
}
