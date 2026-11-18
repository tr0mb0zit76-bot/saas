<?php

namespace Tests\Unit\Mail;

use App\Mail\TenantWelcomeMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Mail\Mailables\Attachment;

/**
 * No RefreshDatabase — mailable content/attachment only; MySQL not required.
 */
class TenantWelcomeMailTest extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_welcome_mail_includes_admin_checklist_and_guide_attachment(): void
    {
        $this->assertFileExists(base_path(TenantWelcomeMail::ADMIN_GUIDE_PATH));

        $tenant = new Tenant([
            'slug' => 'acme-test',
            'name' => 'Acme Forwarding',
            'settings' => ['branding' => ['product_name' => 'Traklo Pro']],
        ]);
        $user = new User([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $mailable = new TenantWelcomeMail($tenant, $user, 'temp-password-xyz');

        $mailable->assertHasSubject('Доступ в Traklo Pro');
        $mailable->assertSeeInText('Краткий чеклист администратора');
        $mailable->assertSeeInText('Заполните реквизиты своей компании');
        $mailable->assertSeeInText(TenantWelcomeMail::ADMIN_GUIDE_ATTACHMENT_NAME);
        $mailable->assertSeeInText('temp-password-xyz');
        $mailable->assertHasAttachment(
            Attachment::fromPath(base_path(TenantWelcomeMail::ADMIN_GUIDE_PATH))
                ->as(TenantWelcomeMail::ADMIN_GUIDE_ATTACHMENT_NAME)
                ->withMime('text/markdown'),
        );
    }
}
