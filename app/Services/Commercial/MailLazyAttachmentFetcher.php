<?php

namespace App\Services\Commercial;

use App\Models\MailMessage;
use App\Models\User;
use App\Support\MailSync\MailImapClient;
use RuntimeException;

final class MailLazyAttachmentFetcher
{
    public function __construct(
        private readonly MailImapClient $imapClient,
        private readonly MailInboundAttachmentStorage $inboundAttachmentStorage,
    ) {}

    /**
     * @return array{original_name: string, file_path: string, storage_driver: string, mime_type: string|null, file_size: int}
     */
    public function materialize(MailMessage $message, int $attachmentIndex): array
    {
        $attachments = $message->attachments;

        if (! is_array($attachments)) {
            throw new RuntimeException('Вложения не найдены.');
        }

        $attachment = $attachments[$attachmentIndex] ?? null;

        if (! is_array($attachment)) {
            throw new RuntimeException('Вложение не найдено.');
        }

        $path = trim((string) ($attachment['file_path'] ?? ''));

        if ($path !== '') {
            return [
                'original_name' => (string) ($attachment['original_name'] ?? 'attachment'),
                'file_path' => $path,
                'storage_driver' => (string) ($attachment['storage_driver'] ?? ''),
                'mime_type' => isset($attachment['mime_type']) ? (string) $attachment['mime_type'] : null,
                'file_size' => (int) ($attachment['file_size'] ?? 0),
            ];
        }

        if (! (bool) ($attachment['lazy'] ?? false)) {
            throw new RuntimeException('Файл вложения недоступен.');
        }

        $mailboxUser = $this->resolveMailboxUser($message);
        $password = $mailboxUser->mail_imap_secret;

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Пароль IMAP не задан.');
        }

        $uid = (int) ($attachment['imap_uid'] ?? 0);
        $folder = trim((string) ($attachment['imap_folder'] ?? ''));
        $part = trim((string) ($attachment['imap_part'] ?? ''));

        if ($uid <= 0 || $folder === '' || $part === '') {
            throw new RuntimeException('IMAP-ссылка на вложение неполная.');
        }

        if (! $this->imapClient->extensionLoaded()) {
            throw new RuntimeException('PHP extension imap не установлена.');
        }

        $fetched = $this->imapClient->fetchAttachmentPart(
            (string) $mailboxUser->email,
            $password,
            $folder,
            $uid,
            $part,
        );

        if ($fetched === null) {
            throw new RuntimeException('Не удалось загрузить вложение с IMAP.');
        }

        $stored = $this->inboundAttachmentStorage->storeForMessage(
            $mailboxUser,
            $message->id,
            [$fetched],
        );

        if ($stored === []) {
            throw new RuntimeException('Не удалось сохранить вложение.');
        }

        $attachments[$attachmentIndex] = $stored[0];
        $message->forceFill(['attachments' => $attachments])->save();

        return $stored[0];
    }

    private function resolveMailboxUser(MailMessage $message): User
    {
        $userId = $message->mailbox_user_id ?? $message->created_by;

        if ($userId === null) {
            throw new RuntimeException('Владелец почтового ящика не найден.');
        }

        $user = User::query()->find($userId);

        if ($user === null || ! $user->hasMailImapCredential()) {
            throw new RuntimeException('IMAP-доступ для ящика недоступен.');
        }

        return $user;
    }
}
