<?php

namespace App\Services\Commercial;

use App\Models\User;
use App\Services\DocumentStorageService;

final class MailInboundAttachmentStorage
{
    public function __construct(
        private readonly DocumentStorageService $documentStorage,
    ) {}

    /**
     * @param  list<array{filename: string, content?: string, mime_type: string|null, size: int, part_number?: string}>  $rawAttachments
     * @return list<array<string, mixed>>
     */
    public function storeForMessage(
        User $mailboxUser,
        int $messageId,
        array $rawAttachments,
        int $imapUid = 0,
        string $imapFolder = '',
    ): array {
        if ($rawAttachments === []) {
            return [];
        }

        $eagerEnabled = (bool) config('mail_sync.inbound_attachments.enabled', false);
        $maxFiles = max(1, (int) config('mail_sync.inbound_attachments.max_files_per_message', 10));
        $maxBytes = max(1024, (int) config('mail_sync.inbound_attachments.max_file_kb', 15360)) * 1024;
        $stored = [];

        foreach (array_slice($rawAttachments, 0, $maxFiles) as $attachment) {
            $content = (string) ($attachment['content'] ?? '');
            $size = (int) ($attachment['size'] ?? strlen($content));
            $partNumber = trim((string) ($attachment['part_number'] ?? ''));

            if ($eagerEnabled && $content !== '' && $size > 0 && $size <= $maxBytes) {
                $meta = $this->documentStorage->storeMailInboundAttachment(
                    $content,
                    (string) ($attachment['filename'] ?? 'attachment'),
                    $mailboxUser->id,
                    $messageId,
                );

                $stored[] = [
                    'original_name' => $meta['original_name'],
                    'file_path' => $meta['file_path'],
                    'storage_driver' => $meta['storage_driver'],
                    'mime_type' => $attachment['mime_type'] ?? $meta['mime_type'],
                    'file_size' => $meta['file_size'],
                ];

                continue;
            }

            if (! $eagerEnabled && $partNumber !== '' && $imapUid > 0 && $imapFolder !== '') {
                $stored[] = [
                    'original_name' => (string) ($attachment['filename'] ?? 'attachment'),
                    'mime_type' => $attachment['mime_type'] ?? null,
                    'file_size' => $size > 0 ? $size : null,
                    'lazy' => true,
                    'imap_uid' => $imapUid,
                    'imap_folder' => $imapFolder,
                    'imap_part' => $partNumber,
                ];
            }
        }

        return $stored;
    }
}
