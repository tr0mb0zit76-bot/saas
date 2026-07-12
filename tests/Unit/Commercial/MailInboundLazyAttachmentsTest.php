<?php

namespace Tests\Unit\Commercial;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\MailInboxSyncService;
use App\Support\MailSync\ImportedMailMessage;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SaasTestCase;

class MailInboundLazyAttachmentsTest extends SaasTestCase
{
    #[Test]
    public function it_stores_lazy_attachment_metadata_when_import_on_sync_is_disabled(): void
    {
        config(['mail_sync.inbound_attachments.enabled' => false]);
        Storage::fake('local');

        $role = Role::query()->create([
            'name' => 'mail_lazy',
            'visibility_areas' => ['mail'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'lazy@example.com',
        ]);

        $imported = new ImportedMailMessage(
            internetMessageId: '<lazy-attach@example.com>',
            direction: MailMessage::DIRECTION_INBOUND,
            fromEmail: 'client@example.com',
            toEmails: ['lazy@example.com'],
            ccEmails: [],
            subject: 'Счёт',
            bodyText: 'Во вложении',
            bodyHtml: null,
            inReplyTo: null,
            sentAt: now(),
            folder: 'INBOX',
            imapUid: 42,
            rawAttachments: [[
                'filename' => 'invoice.pdf',
                'mime_type' => 'application/pdf',
                'size' => 1200,
                'part_number' => '2',
            ]],
        );

        $message = app(MailInboxSyncService::class)->importMessage($user, $imported);

        $this->assertInstanceOf(MailMessage::class, $message);
        $this->assertCount(1, $message->attachments);
        $this->assertTrue($message->attachments[0]['lazy'] ?? false);
        $this->assertSame(42, $message->attachments[0]['imap_uid'] ?? null);
        $this->assertSame('2', $message->attachments[0]['imap_part'] ?? null);
        $this->assertArrayNotHasKey('file_path', $message->attachments[0]);
    }

    #[Test]
    public function it_stores_files_when_import_on_sync_is_enabled(): void
    {
        config(['mail_sync.inbound_attachments.enabled' => true, 'tenant_storage.use_for_documents' => false]);
        Storage::fake('local');

        $role = Role::query()->create([
            'name' => 'mail_eager',
            'visibility_areas' => ['mail'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'eager@example.com',
        ]);

        $imported = new ImportedMailMessage(
            internetMessageId: '<eager-attach@example.com>',
            direction: MailMessage::DIRECTION_INBOUND,
            fromEmail: 'client@example.com',
            toEmails: ['eager@example.com'],
            ccEmails: [],
            subject: 'Документы',
            bodyText: 'См. вложение',
            bodyHtml: null,
            inReplyTo: null,
            sentAt: now(),
            folder: 'INBOX',
            imapUid: 7,
            rawAttachments: [[
                'filename' => 'scan.pdf',
                'content' => '%PDF-1.4 test',
                'mime_type' => 'application/pdf',
                'size' => 13,
            ]],
        );

        $message = app(MailInboxSyncService::class)->importMessage($user, $imported);

        $this->assertInstanceOf(MailMessage::class, $message);
        $this->assertSame('scan.pdf', $message->attachments[0]['original_name'] ?? null);
        Storage::disk('local')->assertExists($message->attachments[0]['file_path']);

        $thread = MailThread::query()->withoutGlobalScopes()->find($message->mail_thread_id);
        $this->assertNotNull($thread);
    }
}
