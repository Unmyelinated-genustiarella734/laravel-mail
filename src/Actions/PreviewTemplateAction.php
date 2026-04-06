<?php

namespace JeffersonGoncalves\LaravelMail\Actions;

use Illuminate\Support\Facades\Blade;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

class PreviewTemplateAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{subject: string, html: string, text: string|null}
     */
    public function execute(MailTemplate $template, array $data = [], ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $mergedData = $this->buildData($template, $data);

        $subject = Blade::render($template->getSubjectForLocale($locale), $mergedData);
        $html = Blade::render($template->getHtmlBodyForLocale($locale), $mergedData);

        $layout = $template->layout ?? config('laravel-mail.templates.default_layout');
        if ($layout) {
            $html = Blade::render($layout, array_merge($mergedData, ['slot' => $html]));
        }

        $text = null;
        if ($template->text_body) {
            $textBody = $template->getTextBodyForLocale($locale);
            $text = $textBody ? Blade::render($textBody, $mergedData) : null;
        }

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildData(MailTemplate $template, array $data): array
    {
        $exampleData = [];

        if ($template->variables) {
            foreach ($template->variables as $variable) {
                $exampleData[$variable['name']] = $variable['example'];
            }
        }

        return array_merge($exampleData, $data);
    }
}
