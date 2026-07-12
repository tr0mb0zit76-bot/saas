<?php

namespace App\Services\Finance;

use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\Saas\TenantAuditLogger;
use Illuminate\Support\Facades\Schema;

class PaymentSchedulePaymentLedgerService
{
    public function __construct(
        private readonly TenantAuditLogger $auditLogger,
    ) {}

    public function ledgerTableExists(): bool
    {
        return Schema::hasTable('payment_schedule_payment_events');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordFromPaymentSchedule(
        PaymentSchedule $schedule,
        float $amount,
        string $paymentDate,
        array $payload,
        ?int $recordedBy,
        ?int $partialScheduleId = null,
    ): ?PaymentSchedulePaymentEvent {
        if (! $this->ledgerTableExists() || $amount <= 0) {
            return null;
        }

        $party = strtolower(trim((string) $schedule->party));
        $contractorId = $this->resolveContractorId($schedule, $party);

        $event = PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $schedule->order_id,
            'contractor_id' => $contractorId,
            'payment_schedule_id' => $partialScheduleId ?? $schedule->id,
            'party' => $party,
            'amount' => round($amount, 2),
            'payment_date' => $paymentDate,
            'payment_method' => $payload['payment_method'] ?? null,
            'transaction_reference' => $payload['transaction_reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'recorded_by' => $recordedBy,
        ]);

        $schedule->loadMissing('order:id,tenant_id');

        $this->auditLogger->log(
            $event->tenant_id ?? $schedule->order?->tenant_id,
            $recordedBy,
            'payment.recorded',
            'payment_schedule_payment_event',
            $event->id,
            null,
            [
                'order_id' => $schedule->order_id,
                'party' => $party,
                'amount' => round($amount, 2),
                'payment_date' => $paymentDate,
                'payment_schedule_id' => $partialScheduleId ?? $schedule->id,
            ],
        );

        return $event;
    }

    private function resolveContractorId(PaymentSchedule $schedule, string $party): ?int
    {
        if ($party === 'carrier') {
            if (Schema::hasColumn('payment_schedules', 'counterparty_id') && $schedule->counterparty_id) {
                return (int) $schedule->counterparty_id;
            }

            $schedule->loadMissing('order:id,customer_id,carrier_id');

            return $schedule->order?->carrier_id ? (int) $schedule->order->carrier_id : null;
        }

        if ($party === 'contractor') {
            if (Schema::hasColumn('payment_schedules', 'counterparty_id') && $schedule->counterparty_id) {
                return (int) $schedule->counterparty_id;
            }

            return null;
        }

        if ($party === 'customer') {
            $schedule->loadMissing('order:id,customer_id');

            return $schedule->order?->customer_id ? (int) $schedule->order->customer_id : null;
        }

        return null;
    }
}
