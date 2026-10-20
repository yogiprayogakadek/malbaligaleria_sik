<?php

namespace App\Services;

use App\Models\MessageTemplate;

class MessageTemplateRenderer
{
    public function render(string $key, array $variables, string $fallbackSubject, string $fallbackBody): array
    {
        $template = MessageTemplate::query()->where('key', $key)->where('is_active', true)->first();
        if (! $template) {
            return ['subject' => $fallbackSubject, 'body' => $fallbackBody];
        }

        $replace = [];
        foreach ($variables as $name => $value) {
            $replace['{{'.$name.'}}'] = (string) $value;
        }

        return [
            'subject' => strtr($template->subject ?: $fallbackSubject, $replace),
            'body' => strtr($template->body, $replace),
        ];
    }
}
