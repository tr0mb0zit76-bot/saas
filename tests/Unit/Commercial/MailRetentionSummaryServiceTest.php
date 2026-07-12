<?php

namespace Tests\Unit\Commercial;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\MailMessage;
use App\Services\Commercial\MailRetentionSummaryService;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\SaasTestCase;

class MailRetentionSummaryServiceTest extends SaasTestCase
{
    #[Test]
    public function it_uses_llm_summary_when_available(): void
    {
        config(['commercial_intelligence.mail_retention.ai_summary' => true]);

        $chat = new class implements ChatCompletionClient
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $options = []): string
            {
                return 'Клиент уточнил сроки доставки. Мы подтвердили ставку без изменений.';
            }
        };

        $service = new MailRetentionSummaryService($chat, new ExternalLlmPayloadSanitizer);

        $message = new MailMessage([
            'subject' => 'Re: перевозка',
            'direction' => MailMessage::DIRECTION_INBOUND,
            'from_email' => 'client@example.com',
        ]);

        $summary = $service->build($message, 'Длинный текст письма про маршрут и цену.');

        $this->assertStringContainsString('Клиент уточнил', $summary);
    }

    #[Test]
    public function it_falls_back_to_truncation_when_llm_unavailable(): void
    {
        config([
            'commercial_intelligence.mail_retention.ai_summary' => true,
            'commercial_intelligence.mail_retention.summary_max_chars' => 20,
        ]);

        $chat = new class implements ChatCompletionClient
        {
            public function isAvailable(): bool
            {
                return false;
            }

            public function chat(array $messages, array $options = []): string
            {
                return 'unused';
            }
        };

        $service = new MailRetentionSummaryService($chat, new ExternalLlmPayloadSanitizer);

        $message = new MailMessage(['subject' => 'Test']);

        $summary = $service->build($message, '01234567890123456789012345');

        $this->assertSame('01234567890123456789', mb_substr($summary, 0, 20));
    }
}
