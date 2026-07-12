<?php

namespace App\Services\Commercial;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\MailMessage;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use Illuminate\Support\Str;
use Throwable;

final class MailRetentionSummaryService
{
    public function __construct(
        private readonly ChatCompletionClient $chat,
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
    ) {}

    public function build(MailMessage $message, string $body): string
    {
        $body = trim($body);
        $maxChars = (int) config('commercial_intelligence.mail_retention.summary_max_chars', 500);

        if ($body === '') {
            return trim((string) ($message->retention_summary ?? '')) ?: '—';
        }

        if (! (bool) config('commercial_intelligence.mail_retention.ai_summary', true)
            || ! $this->chat->isAvailable()) {
            return Str::limit($body, $maxChars);
        }

        try {
            $messages = $this->sanitizer->sanitizeMessages([
                ['role' => 'system', 'content' => <<<'TEXT'
Ты архивариус CRM экспедиторской компании. Письмо будет удалено из хранилища; оставь короткий конспект на русском для будущего контекста переписки с контрагентом.

Правила:
- 2–4 предложения, только суть: о чём письмо, решения, обязательства, открытые вопросы.
- Без email, телефонов и ФИО — роли («клиент», «мы»).
- Без markdown и JSON — только plain text.
TEXT],
                ['role' => 'user', 'content' => $this->userPrompt($message, $body)],
            ], 'command_bar');

            $summary = trim($this->chat->chat($messages, [
                'temperature' => (float) config('ai.mail_retention.temperature', 0.2),
                'max_tokens' => (int) config('ai.mail_retention.max_tokens', 256),
            ]));

            if ($summary !== '') {
                return Str::limit($summary, $maxChars);
            }
        } catch (Throwable) {
            // ponytail: fallback keeps purge job running without LLM
        }

        return Str::limit($body, $maxChars);
    }

    private function userPrompt(MailMessage $message, string $body): string
    {
        $subject = trim((string) $message->subject);
        $direction = (string) $message->direction;
        $sentAt = $message->sent_at?->toDateString() ?? '';

        return implode("\n", array_filter([
            'Тема: '.($subject !== '' ? $subject : '(без темы)'),
            "Направление: {$direction}",
            $sentAt !== '' ? "Дата: {$sentAt}" : null,
            '',
            'Текст:',
            Str::limit($body, (int) config('commercial_intelligence.mail_retention.ai_input_max_chars', 4000)),
        ]));
    }
}
