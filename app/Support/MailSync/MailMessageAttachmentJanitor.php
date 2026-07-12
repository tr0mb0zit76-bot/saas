<?php

namespace App\Support\MailSync;

use App\Models\MailMessage;
use App\Services\DocumentStorageService;

final class MailMessageAttachmentJanitor
{
    public function __construct(
        private readonly DocumentStorageService $documentStorage,
    ) {}

    public function deleteStoredFiles(MailMessage $message): void
    {
        $attachments = $message->attachments;

        if (! is_array($attachments)) {
            return;
        }

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $path = trim((string) ($attachment['file_path'] ?? ''));

            if ($path === '') {
                continue;
            }

            $driver = isset($attachment['storage_driver']) ? (string) $attachment['storage_driver'] : null;
            $this->documentStorage->delete($path, $driver);
        }
    }

    /**
     * @return list<array{original_name: string, file_size: int|null, mime_type: string|null}>
     */
    public function retentionMetadata(MailMessage $message): array
    {
        $attachments = $message->attachments;

        if (! is_array($attachments)) {
            return [];
        }

        $kept = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $name = trim((string) ($attachment['original_name'] ?? $attachment['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $kept[] = [
                'original_name' => $name,
                'file_size' => isset($attachment['file_size']) ? (int) $attachment['file_size'] : null,
                'mime_type' => isset($attachment['mime_type']) ? (string) $attachment['mime_type'] : null,
            ];
        }

        return $kept;
    }
}
