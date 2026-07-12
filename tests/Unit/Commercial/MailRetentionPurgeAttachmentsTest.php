<?php

namespace Tests\Unit\Commercial;

use App\Console\Commands\PurgeMailMessageBodiesCommand;
use App\Models\MailMessage;
use App\Models\MailThread;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SaasTestCase;

class MailRetentionPurgeAttachmentsTest extends SaasTestCase
{
    #[Test]
    public function purge_removes_stored_attachment_files_and_keeps_metadata(): void
    {
        config(['tenant_storage.use_for_documents' => false]);
        Storage::fake('local');

        $path = 'mail_inbound/1/1/test.pdf';
        Storage::disk('local')->put($path, 'pdf-content');

        $thread = MailThread::query()->create([
            'subject' => 'Old thread',
            'last_message_at' => now()->subMonths(7),
        ]);

        $message = MailMessage::query()->create([
            'mail_thread_id' => $thread->id,
            'direction' => MailMessage::DIRECTION_INBOUND,
            'internet_message_id' => '<purge-attach@test>',
            'from_email' => 'a@b.com',
            'to_emails' => ['c@d.com'],
            'subject' => 'Old',
            'body_text' => str_repeat('Текст письма. ', 40),
            'sent_at' => now()->subMonths(7),
            'attachments' => [[
                'original_name' => 'test.pdf',
                'file_path' => $path,
                'storage_driver' => 'local',
                'mime_type' => 'application/pdf',
                'file_size' => 11,
            ]],
        ]);

        Artisan::call(PurgeMailMessageBodiesCommand::class, ['--months' => 6]);

        Storage::disk('local')->assertMissing($path);

        $message->refresh();
        $this->assertNotNull($message->content_purged_at);
        $this->assertNull($message->body_text);
        $this->assertNotEmpty($message->retention_summary);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('test.pdf', $message->attachments[0]['original_name'] ?? null);
        $this->assertArrayNotHasKey('file_path', $message->attachments[0]);
    }
}
