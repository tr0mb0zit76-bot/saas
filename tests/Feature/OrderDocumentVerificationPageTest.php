<?php

namespace Tests\Feature;

use App\Models\OrderDocument;
use App\Support\PrintFormVerificationCode;
use App\Support\TenantContext;
use Tests\TestCase;

class OrderDocumentVerificationPageTest extends TestCase
{
    public function test_public_verification_page_shows_document_hash_for_valid_code(): void
    {
        $this->enableDocumentVerification();

        $document = OrderDocument::factory()->create([
            'number' => 'REQ-1',
            'metadata' => [
                'pdf_certified_sha256' => str_repeat('a', 64),
                'pdf_certified_at' => '2026-06-12T10:00:00+04:00',
            ],
        ]);

        $response = $this->get(route('print-verification.order-documents.show', [
            'orderDocument' => $document,
            'code' => PrintFormVerificationCode::forOrderDocument($document),
        ]));

        $response->assertOk();
        $response->assertSee('Проверка целостности документа');
        $response->assertSee(str_repeat('a', 64));
        $response->assertDontSee('Сравните хеш', false);
        $response->assertDontSee('Сверьте этот хеш', false);
    }

    public function test_public_verification_page_returns_not_found_for_invalid_code(): void
    {
        $this->enableDocumentVerification();

        $document = OrderDocument::factory()->create();

        $this->get(route('print-verification.order-documents.show', [
            'orderDocument' => $document,
            'code' => 'bad-code',
        ]))->assertNotFound();
    }

    public function test_public_verification_page_returns_not_found_when_tenant_setting_off(): void
    {
        $tenant = TenantContext::get();
        if ($tenant !== null) {
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            data_set($settings, 'print.document_verification', false);
            $tenant->forceFill(['settings' => $settings])->save();
            TenantContext::set($tenant->fresh());
        }

        $document = OrderDocument::factory()->create();

        $this->get(route('print-verification.order-documents.show', [
            'orderDocument' => $document,
            'code' => PrintFormVerificationCode::forOrderDocument($document),
        ]))->assertNotFound();
    }

    private function enableDocumentVerification(): void
    {
        $tenant = TenantContext::get();
        if ($tenant === null) {
            $this->markTestSkipped('No default tenant in test DB.');
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        data_set($settings, 'print.document_verification', true);
        $tenant->forceFill(['settings' => $settings])->save();
        TenantContext::set($tenant->fresh());
    }
}
