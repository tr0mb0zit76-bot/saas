<?php

namespace App\Services\Saas;

use App\Models\SubscriptionPlan;
use App\Models\TenantInvoice;
use App\Services\Commercial\LeadProposalPdfService;
use Illuminate\Support\Facades\View;

final class TenantInvoicePdfService
{
    public function __construct(
        private readonly LeadProposalPdfService $pdfConverter,
    ) {}

    public function renderPdf(TenantInvoice $invoice): ?string
    {
        $html = View::make('platform.tenant-invoice', [
            'invoice' => $invoice->loadMissing('tenant'),
            'tenant' => $invoice->tenant,
            'planLabel' => SubscriptionPlan::findByKey($invoice->tenant?->planKey() ?? 'start')?->label
                ?? config('saas-plans.plans.'.$invoice->tenant?->planKey().'.label', 'Start'),
        ])->render();

        return $this->pdfConverter->convertHtmlToPdf($html, $invoice->invoice_number.'.pdf');
    }
}
