<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            lead="Управление арендаторами Traklo Pro: создание, тариф, статус."
            title="Арендаторы SaaS"
        >
            <template #actions>
                <button type="button" :class="crmBtnCreate" @click="showCreate = !showCreate">
                    {{ showCreate ? 'Скрыть форму' : 'Новый арендатор' }}
                </button>
            </template>
        </CrmPageHeader>

        <div v-if="showCreate" :class="`${crmPanel} space-y-4 p-4`">
            <div class="text-sm font-medium">Создание арендатора</div>
            <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="submitCreate">
                <div class="space-y-1">
                    <label class="text-xs uppercase tracking-wide text-zinc-500">Slug (subdomain)</label>
                    <input v-model="createForm.slug" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="acme" />
                    <p v-if="createForm.errors.slug" class="text-xs text-rose-600">{{ createForm.errors.slug }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-xs uppercase tracking-wide text-zinc-500">Название</label>
                    <input v-model="createForm.name" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    <p v-if="createForm.errors.name" class="text-xs text-rose-600">{{ createForm.errors.name }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-xs uppercase tracking-wide text-zinc-500">Тариф</label>
                    <select v-model="createForm.plan" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        <option v-for="plan in planOptions" :key="plan.value" :value="plan.value">{{ plan.label }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xs uppercase tracking-wide text-zinc-500">Статус</label>
                    <select v-model="createForm.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-2 xl:col-span-4">
                    <button type="submit" :class="crmBtnCreate" :disabled="createForm.processing">Создать</button>
                </div>
            </form>
        </div>

        <div :class="crmGridPanel">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-800">
                    <tr>
                        <th class="px-3 py-2">Slug</th>
                        <th class="px-3 py-2">Название</th>
                        <th class="px-3 py-2">Тариф</th>
                        <th class="px-3 py-2">Статус</th>
                        <th class="px-3 py-2">Пользователи</th>
                        <th class="px-3 py-2">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tenant in tenants" :key="tenant.id" class="border-b border-zinc-100 dark:border-zinc-900">
                        <td class="px-3 py-2 font-mono text-xs">{{ tenant.slug }}</td>
                        <td class="px-3 py-2">
                            <input
                                v-model="editDrafts[tenant.id].name"
                                type="text"
                                class="w-full min-w-[160px] rounded-lg border border-zinc-200 px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <select v-model="editDrafts[tenant.id].plan" class="rounded-lg border border-zinc-200 px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                                <option v-for="plan in planOptions" :key="plan.value" :value="plan.value">{{ plan.label }}</option>
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            <select v-model="editDrafts[tenant.id].status" class="rounded-lg border border-zinc-200 px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                                <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                        </td>
                        <td class="px-3 py-2">{{ tenant.users_count }}</td>
                        <td class="px-3 py-2">
                            <button type="button" :class="crmBtnNeutral" :disabled="savingId === tenant.id" @click="saveTenant(tenant.id)">
                                Сохранить
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate, crmBtnNeutral, crmGridPanel, crmPanel } from '@/styles/crm';
import { router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'platform-tenants' }, () => page),
});

const props = defineProps({
    tenants: { type: Array, default: () => [] },
    planOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
});

const showCreate = ref(false);
const savingId = ref(null);

const createForm = useForm({
    slug: '',
    name: '',
    plan: 'start',
    status: 'trial',
    trial_ends_at: null,
});

const editDrafts = reactive({});

for (const tenant of props.tenants) {
    editDrafts[tenant.id] = {
        name: tenant.name,
        plan: tenant.plan,
        status: tenant.status,
        trial_ends_at: tenant.trial_ends_at,
    };
}

function submitCreate() {
    createForm.post(route('platform.tenants.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset('slug', 'name');
            showCreate.value = false;
        },
    });
}

function saveTenant(tenantId) {
    savingId.value = tenantId;
    router.patch(route('platform.tenants.update', tenantId), editDrafts[tenantId], {
        preserveScroll: true,
        onFinish: () => {
            savingId.value = null;
        },
    });
}
</script>
