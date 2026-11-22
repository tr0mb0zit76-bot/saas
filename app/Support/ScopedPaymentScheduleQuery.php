<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One tenant-scoped payment_schedules query for cashflow / WC / export / MCP.
 * Raw DB::table bypasses Eloquent TenantScope — always fail-closed via TenantContext::restrict.
 */
final class ScopedPaymentScheduleQuery
{
    public static function base(?User $user): Builder
    {
        $area = $user !== null
            ? RoleAccess::resolvePaymentScheduleVisibilityAreaForUser($user)
            : 'orders';

        $query = DB::table('payment_schedules')
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id')
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($q) => $q->whereNull('orders.deleted_at'),
            )
            ->whereNotIn('payment_schedules.status', ['cancelled'])
            ->when(
                Schema::hasColumn('payment_schedules', 'parent_payment_id'),
                fn ($q) => $q->whereNull('payment_schedules.parent_payment_id'),
            )
            ->when(
                Schema::hasColumn('payment_schedules', 'is_partial'),
                fn ($q) => $q->where(function ($inner): void {
                    $inner->whereNull('payment_schedules.is_partial')
                        ->orWhere('payment_schedules.is_partial', false);
                }),
            );

        if (Schema::hasColumn('payment_schedules', 'tenant_id')) {
            TenantContext::restrict($query, 'payment_schedules.tenant_id');
        } elseif (Schema::hasColumn('orders', 'tenant_id')) {
            TenantContext::restrict($query, 'orders.tenant_id');
        }

        if ($user !== null) {
            OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $user, $area);
        }

        return $query;
    }

    /**
     * SQL expression: contractor id for AR (customer) / AP (carrier) rows.
     */
    public static function counterpartyIdSql(): string
    {
        $carrierExpr = Schema::hasColumn('payment_schedules', 'counterparty_id')
            ? 'COALESCE(payment_schedules.counterparty_id, orders.carrier_id)'
            : 'orders.carrier_id';

        return "CASE
            WHEN LOWER(TRIM(payment_schedules.party)) = 'customer' THEN orders.customer_id
            ELSE {$carrierExpr}
        END";
    }
}
