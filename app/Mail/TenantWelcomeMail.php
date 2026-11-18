<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantHost;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantWelcomeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Platform-owned launch checklist (no tenant PII). Same text as docs/sync/tenant-admin-crm-launch.md.
     */
    public const ADMIN_GUIDE_PATH = 'docs/sync/tenant-admin-crm-launch.md';

    public const ADMIN_GUIDE_ATTACHMENT_NAME = 'tenant-admin-guide.md';

    public function __construct(
        public Tenant $tenant,
        public User $user,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        $productName = $this->tenant->branding()['product_name'];

        return new Envelope(
            subject: "Доступ в {$productName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.tenant-welcome',
            with: [
                'tenantName' => $this->tenant->name,
                'userName' => $this->user->name,
                'loginUrl' => TenantHost::url($this->tenant->slug, '/login'),
                'email' => $this->user->email,
                'temporaryPassword' => $this->temporaryPassword,
                'productName' => $this->tenant->branding()['product_name'],
                'hasAdminGuideAttachment' => $this->adminGuideAbsolutePath() !== null,
                'adminGuideAttachmentName' => self::ADMIN_GUIDE_ATTACHMENT_NAME,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $path = $this->adminGuideAbsolutePath();

        if ($path === null) {
            return [];
        }

        return [
            Attachment::fromPath($path)
                ->as(self::ADMIN_GUIDE_ATTACHMENT_NAME)
                ->withMime('text/markdown'),
        ];
    }

    public function adminGuideAbsolutePath(): ?string
    {
        $path = base_path(self::ADMIN_GUIDE_PATH);

        return is_file($path) ? $path : null;
    }
}
