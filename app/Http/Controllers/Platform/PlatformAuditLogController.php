<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        TenantContext::bypass(true);

        $query = TenantAuditLog::query()
            ->with([
                'tenant:id,slug,name',
                'user:id,name,email',
            ])
            ->orderByDesc('id');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        $logs = $query
            ->paginate(25)
            ->withQueryString()
            ->through(fn (TenantAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
                'tenant' => $log->tenant ? [
                    'id' => $log->tenant->id,
                    'slug' => $log->tenant->slug,
                    'name' => $log->tenant->name,
                ] : null,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
            ]);

        $tenants = Tenant::query()
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
            ])
            ->values();

        TenantContext::bypass(false);

        return Inertia::render('Platform/Audit/Index', [
            'logs' => $logs,
            'tenants' => $tenants,
            'filters' => [
                'tenant_id' => $request->input('tenant_id'),
                'action' => $request->input('action'),
            ],
            'actionOptions' => [
                ['value' => 'tenant.created', 'label' => 'Создание арендатора'],
                ['value' => 'tenant.updated', 'label' => 'Изменение арендатора'],
                ['value' => 'tenant.invoice_paid', 'label' => 'Оплата счёта'],
                ['value' => 'tenant.features_updated', 'label' => 'Модули арендатора'],
                ['value' => 'tenant.demo_signup', 'label' => 'Демо-регистрация'],
                ['value' => 'plan.updated', 'label' => 'Изменение тарифа'],
                ['value' => 'order.status_changed', 'label' => 'Статус заказа'],
                ['value' => 'role.updated', 'label' => 'Изменение роли'],
                ['value' => 'role.created', 'label' => 'Создание роли'],
                ['value' => 'user.created', 'label' => 'Создание пользователя'],
                ['value' => 'payment.recorded', 'label' => 'Оплата по графику'],
            ],
        ]);
    }
}
