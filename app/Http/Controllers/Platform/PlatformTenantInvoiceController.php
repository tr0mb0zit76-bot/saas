<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Services\Saas\TenantInvoicePdfService;
use App\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class PlatformTenantInvoiceController extends Controller
{
    public function pdf(Tenant $tenant, TenantInvoice $invoice, TenantInvoicePdfService $pdfService): Response
    {
        TenantContext::bypass(true);

        abort_unless((int) $invoice->tenant_id === (int) $tenant->id, 404);

        $pdf = $pdfService->renderPdf($invoice);

        TenantContext::bypass(false);

        abort_if($pdf === null || $pdf === '', 503, 'PDF недоступен: проверьте настройку Gotenberg (DOC_PREVIEW_DRIVER=gotenberg, GOTENBERG_URL).');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }
}
