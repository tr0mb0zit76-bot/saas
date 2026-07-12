<?php

namespace App\Console\Commands;

use App\Models\MailMessage;
use App\Services\Commercial\MailRetentionSummaryService;
use App\Support\MailSync\MailMessageAttachmentJanitor;
use App\Support\MailSync\MailMessageBodyPresenter;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PurgeMailMessageBodiesCommand extends Command
{
    protected $signature = 'mail:purge-non-important-bodies {--months= : Порог в месяцах (по умолчанию из config)}';

    protected $description = 'Сохраняет краткий контекст и очищает тело неважных писем старше порога';

    public function __construct(
        private readonly MailMessageAttachmentJanitor $attachmentJanitor,
        private readonly MailRetentionSummaryService $retentionSummary,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('mail_messages')) {
            $this->warn('Таблица mail_messages отсутствует.');

            return self::SUCCESS;
        }

        $months = (int) ($this->option('months')
            ?: config('commercial_intelligence.mail_retention.purge_older_than_months', 6));
        $cutoff = Carbon::now()->subMonths($months);

        $query = MailMessage::query()
            ->where('is_important', false)
            ->whereNull('content_purged_at')
            ->where(function ($q) use ($cutoff): void {
                $q->where('sent_at', '<', $cutoff)
                    ->orWhere(function ($q2) use ($cutoff): void {
                        $q2->whereNull('sent_at')->where('created_at', '<', $cutoff);
                    });
            });

        $count = 0;

        TenantContext::bypass(true);

        $query->orderBy('id')->chunkById(100, function ($messages) use (&$count): void {
            foreach ($messages as $message) {
                $body = MailMessageBodyPresenter::plainText($message) ?? '';

                $this->attachmentJanitor->deleteStoredFiles($message);

                $message->forceFill([
                    'retention_summary' => $this->retentionSummary->build($message, $body),
                    'body_text' => null,
                    'body_html' => null,
                    'attachments' => $this->attachmentJanitor->retentionMetadata($message),
                    'content_purged_at' => now(),
                ])->save();

                $count++;
            }
        });

        TenantContext::bypass(false);

        $this->info("Обработано сообщений: {$count} (старше {$months} мес., не «важно»).");

        return self::SUCCESS;
    }
}
