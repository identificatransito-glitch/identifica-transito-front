<script setup lang="ts">
import HeroSection from '~/components/dashboard/HeroSection.vue';
import StatsCard from '~/components/dashboard/StatsCard.vue';
import RecentOrdersPanel from '~/components/dashboard/RecentOrdersPanel.vue';
import DashboardPeriodFilter from '~/components/dashboard/DashboardPeriodFilter.vue';
import ManagementCharts from '~/components/dashboard/ManagementCharts.vue';
import { CarFront, CircleDollarSign, PackageCheck, Trophy, UsersRound } from '@lucide/vue';

definePageMeta({ layout: 'dashboard' });

useHead({
    title: 'Dashboard SuperAdmin - Identifica Trânsito',
    meta: [{ name: 'description', content: 'Painel administrativo geral.' }]
});

const { $api } = useNuxtApp();

// Date filter — default: first day of year → today
const today = new Date();
const firstOfYear = new Date(today.getFullYear(), 0, 1);
const dateFrom = ref(firstOfYear.toISOString().split('T')[0]);
const dateTo = ref(today.toISOString().split('T')[0]);
const periodFilterRef = ref<{ open: (event: Event) => void } | null>(null);

const openPeriodFilter = (event: Event) => {
    periodFilterRef.value?.open(event);
};

// Interfaces
interface Totals {
    users: number;
    vehicles: number;
    orders: number;
    revenue: number;
}

interface LatestUser {
    id: number;
    name: string;
    email: string;
    phone: string;
    type: number;
    created_at: string;
}

interface LatestOrder {
    id: number;
    mp_payment_id: string;
    mp_payment_status: string;
    payment_method: string;
    qty: number;
    price: string;
    price_total: string;
    status: number;
    created_at: string;
    tag_name?: string | null;
    tag_slug?: string | null;
    shipment?: { status?: number | string | null } | null;
    vehicle: {
        id: number;
        plate: string;
        name: string;
        color: string;
        year: string;
    } | null;
}

interface DashboardData {
    totals: Totals;
    latest_users: LatestUser[];
    latest_orders: LatestOrder[];
    tag_sales?: Array<{ tag_name?: string; tag_slug?: string; qty?: number; quantity?: number }>;
    balance_history?: Array<{ date?: string; value?: number; balance?: number }>;
}

interface PdvCommission {
    staff_count: number;
    pdv_id: number | null;
    pdv_name: string | null;
    commission_type: 1 | 2 | null;
    commission_type_label: string | null;
    commission_value: number | null;
    total_sales_qty: number;
    total_sales_amount: number;
    commission_amount: number;
}

// State
const loading = ref(false);
const dashboardData = ref<DashboardData | null>(null);

const totals = computed(() => dashboardData.value?.totals ?? { users: 0, vehicles: 0, orders: 0, revenue: 0 });
const latestUsers = computed(() => dashboardData.value?.latest_users ?? []);
const latestOrders = computed(() => dashboardData.value?.latest_orders ?? []);
const tagSales = computed(() => dashboardData.value?.tag_sales ?? []);
const balanceHistory = computed(() => dashboardData.value?.balance_history ?? []);
const recentOrderCards = computed(() => latestOrders.value.slice(0, 4).map((order) => {
    const paymentStatus = String(order.mp_payment_status ?? '').toLowerCase();
    const shipmentStatus = String(order.shipment?.status ?? '').toLowerCase();
    const status: 'em-andamento' | 'em-rota' | 'entregue' | 'cancelado' =
        ['4', 'entregue', 'delivered'].includes(shipmentStatus)
            ? 'entregue'
            : ['5', '6', 'failed', 'returned', 'devolvido'].includes(shipmentStatus) || ['cancelled', 'cancelado', 'rejected', 'rejeitado'].includes(paymentStatus)
                ? 'cancelado'
                : ['2', '3', 'in_transit', 'out_for_delivery', 'em trânsito', 'em transito'].includes(shipmentStatus)
                    ? 'em-rota'
                    : 'em-andamento';

    return {
        id: `#PED-${order.mp_payment_id || order.id}`,
        title: `${order.tag_name || order.vehicle?.name || `Pedido ${order.id}`} - ${order.qty} ${order.qty === 1 ? 'unidade' : 'unidades'}`,
        status
    };
}));

const fetchDashboard = async () => {
    loading.value = true;
    try {
        const res = await $api(`/admin/dashboard?date_from=${dateFrom.value}&date_to=${dateTo.value}`) as any;
        dashboardData.value = res?.data ?? res;
    } catch (e) {
        console.error('Erro ao carregar dashboard:', e);
    } finally {
        loading.value = false;
    }
};

// ── PDV Commissions (repasses) — SuperAdmin only, uses the same date filter above ──
const commissions = ref<PdvCommission[]>([]);
const loadingCommissions = ref(false);
const rankedCommissions = computed(() => [...commissions.value].sort((first, second) => Number(second.total_sales_amount) - Number(first.total_sales_amount)));
const topCommissions = computed(() => rankedCommissions.value.slice(0, 6));

const fetchCommissions = async () => {
    loadingCommissions.value = true;
    try {
        const params = new URLSearchParams({ per_page: '100' });
        if (dateFrom.value) params.set('date_from', dateFrom.value);
        if (dateTo.value) params.set('date_to', dateTo.value);
        const res = await $api(`/admin/pdv-commissions?${params}`) as any;
        commissions.value = Array.isArray(res?.data) ? res.data : [];
    } catch (e) {
        console.error('Erro ao carregar repasses de PDV:', e);
    } finally {
        loadingCommissions.value = false;
    }
};

const applyFilters = () => {
    fetchDashboard();
    fetchCommissions();
};

// Helpers
const formatCurrency = (value: number | string) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    // Already formatted (dd/mm/yyyy HH:mm:ss) — return date part only
    if (dateStr.includes('/')) return dateStr.split(' ')[0];
    return new Date(dateStr).toLocaleDateString('pt-BR');
};

const userTypeLabel = (type: number | string) => {
    if (Number(type) === 1) return 'SuperAdmin';
    if (Number(type) === 2) return 'Admin';
    return 'Cliente';
};


onMounted(() => {
    fetchDashboard();
    fetchCommissions();
});
</script>

<template>
    <div class="admin-page space-y-6 md:space-y-7">
        <HeroSection
            title="Dashboard"
            subtitle="Monitore os principais indicadores e a operação completa da plataforma."
            greeting="Visão estratégica"
            :showButton="false"
            :showFilterButton="true"
            filterLabel="Selecionar período"
            @filter="openPeriodFilter"
        />

        <DashboardPeriodFilter
            ref="periodFilterRef"
            v-model:date-from="dateFrom"
            v-model:date-to="dateTo"
            :loading="loading || loadingCommissions"
            @apply="applyFilters"
        />

        <!-- Stats Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
            <StatsCard
                title="Total de Usuários Ativos"
                :value="totals.users"
                :icon="UsersRound"
                color="blue"
                :loading="loading"
            />
            <StatsCard
                title="Total de Automóveis"
                :value="totals.vehicles"
                :icon="CarFront"
                color="green"
                :loading="loading"
            />
            <StatsCard
                title="Total de Pedidos"
                :value="totals.orders"
                :icon="PackageCheck"
                color="orange"
                :loading="loading"
            />
            <StatsCard
                title="Total de Receita"
                :value="formatCurrency(totals.revenue)"
                :icon="CircleDollarSign"
                color="purple"
                :loading="loading"
            />
        </section>

        <ManagementCharts
            :latest-orders="latestOrders"
            :tag-sales="tagSales"
            :balance-history="balanceHistory"
            :date-from="dateFrom"
            :date-to="dateTo"
            :loading="loading"
        />

        <!-- Melhores PDVs -->
        <section class="management-chart-card">
            <header>
                <span><Trophy :size="20" :stroke-width="1.9" /></span>
                <div><h2>Melhores PDVs</h2><p>Ranking por receita no período selecionado acima.</p></div>
                <NuxtLink to="/dashboard/superadmin/pontos-de-venda" class="ml-auto shrink-0 self-center whitespace-nowrap rounded-md px-2 py-1 text-[12.25px] font-semibold text-[#1f46ee] transition-colors hover:bg-[#dbe5ff] hover:text-[#1739d4]">Ver todos</NuxtLink>
            </header>
            <div class="mt-5">
                <!-- Skeleton -->
                <div v-if="loadingCommissions" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Skeleton v-for="n in 3" :key="n" height="180px" class="rounded-xl!" />
                </div>

                <!-- Cards -->
                <div v-else-if="topCommissions.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="item in topCommissions"
                        :key="item.pdv_id"
                        class="rounded-xl border border-[#e1e6ee] bg-white p-4 transition-shadow hover:shadow-sm"
                    >
                        <div class="mb-4 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-[#0c0f1a]">{{ item.pdv_name }}</p>
                                <p class="truncate text-sm text-[#52667f]">{{ item.staff_count }} acesso(s) vinculado(s)</p>
                            </div>
                            <span
                                v-if="item.commission_type_label"
                                class="shrink-0 inline-flex items-center rounded-full bg-[#f1f5f9] px-2 py-0.5 text-[10px] font-semibold text-[#64748b]"
                            >
                                {{ item.commission_type_label }}
                            </span>
                        </div>

                        <div class="mb-3 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-[#f7f9fc] p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Etiquetas vendidas</p>
                                <p class="mt-1 text-lg font-bold text-[#172b4d]">{{ item.total_sales_qty }}</p>
                            </div>
                            <div class="rounded-lg bg-[#f7f9fc] p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Receita no período</p>
                                <p class="mt-1 text-lg font-bold text-[#172b4d]">{{ formatCurrency(item.total_sales_amount) }}</p>
                            </div>
                        </div>

                        <div v-if="item.commission_value !== null" class="mb-3 flex items-center justify-between text-sm text-[#64748b]">
                            <span>Taxa de repasse</span>
                            <span class="font-semibold text-[#52667f]">
                                {{ item.commission_value == null ? 'Não configurado' : `${Number(item.commission_value).toLocaleString('pt-BR')}%` }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between border-t border-[#e8edf5] pt-3">
                            <span class="text-sm font-medium text-[#52667f]">Valor a repassar</span>
                            <span class="text-[19px] font-bold text-[#172b4d]">{{ formatCurrency(item.commission_amount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Empty -->
                <div v-else class="flex flex-col items-center justify-center py-12 gap-2 text-gray-400">
                    <i class="pi pi-wallet text-4xl"></i>
                    <p>Nenhum repasse de PDV no período selecionado</p>
                </div>

            </div>
        </section>

        <!-- Bottom panels -->
        <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <!-- Recent Users Table -->
            <div class="order-2 md:order-none xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-3.5">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#eef2ff] text-[#1f46ee]"><UsersRound :size="19" :stroke-width="2" aria-hidden="true" /></span>
                        <h3 class="relative top-[-9px] mb-0! text-lg! font-semibold text-[#0c0f1a]">Últimos Usuários</h3>
                    </div>
                    <NuxtLink to="/dashboard/superadmin/usuarios" class="shrink-0 self-center whitespace-nowrap rounded-md px-2 py-1 text-[12.25px] font-semibold text-[#1f46ee] transition-colors hover:bg-[#dbe5ff] hover:text-[#1739d4]">Ver todos</NuxtLink>
                </div>

                <!-- Skeleton -->
                <div v-if="loading" class="space-y-3">
                    <div v-for="n in 5" :key="n" class="flex gap-3 items-center">
                        <Skeleton shape="circle" width="36px" height="36px" />
                        <div class="flex-1 space-y-1">
                            <Skeleton width="40%" height="14px" />
                            <Skeleton width="60%" height="12px" />
                        </div>
                        <Skeleton width="15%" height="20px" />
                        <Skeleton width="15%" height="12px" />
                    </div>
                </div>

                <template v-else-if="latestUsers.length > 0">
                <div class="dashboard-recent-users-table hidden overflow-hidden rounded-xl border border-[#e8edf5] md:block">
                    <table class="w-full table-fixed text-sm">
                        <thead>
                            <tr class="border-b border-[#e5eaf1] bg-[#f7f9fc]">
                                <th class="px-4 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#52667f]">Nome</th>
                                <th class="px-4 py-3.5 text-center text-[11px] font-bold uppercase tracking-wide text-[#52667f]">E-mail</th>
                                <th class="px-4 py-3.5 text-center text-[11px] font-bold uppercase tracking-wide text-[#52667f]">Tipo</th>
                                <th class="px-4 py-3.5 text-center text-[11px] font-bold uppercase tracking-wide text-[#52667f]">Cadastro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="u in latestUsers"
                                :key="u.id"
                                class="border-b border-[#edf1f5] last:border-b-0 hover:bg-[#f7f9ff] transition-colors"
                            >
                                <td class="px-4 py-3 font-semibold text-[#172b4d]">{{ u.name }}</td>
                                <td class="break-words px-4 py-3 text-center text-[#52667f]">{{ u.email }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="rounded-full bg-[#f1f5f9] px-2 py-0.5 text-xs font-medium text-[#64748b]">
                                        {{ userTypeLabel(u.type) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-[#64748b]">{{ formatDate(u.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="grid gap-3 md:hidden">
                    <article v-for="u in latestUsers" :key="u.id" class="rounded-2xl border border-[#e4eaf2] bg-white p-4">
                        <header class="flex items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#eef2ff] text-[#1f46ee]"><UsersRound :size="20" :stroke-width="2" /></span>
                            <div class="min-w-0 flex-1"><p class="mb-0 truncate text-sm font-bold text-[#172b4d]">{{ u.name }}</p><p class="mt-0.5 mb-0 truncate text-xs text-[#52667f]">{{ u.email }}</p></div>
                            <span class="shrink-0 rounded-full bg-[#f1f5f9] px-2 py-1 text-xs font-medium text-[#64748b]">{{ userTypeLabel(u.type) }}</span>
                        </header>
                        <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-[#edf1f6] pt-3 text-center text-xs text-[#64748b]"><div><dt class="mb-1 text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Tipo</dt><dd class="m-0 font-semibold text-[#172b4d]">{{ userTypeLabel(u.type) }}</dd></div><div><dt class="mb-1 text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Cadastro</dt><dd class="m-0 font-semibold text-[#172b4d]">{{ formatDate(u.created_at) }}</dd></div></dl>
                    </article>
                </div>
                </template>

                <div v-else class="flex flex-col items-center justify-center py-8 gap-2 text-gray-400">
                    <i class="pi pi-users text-3xl"></i>
                    <p class="text-sm">Nenhum usuário encontrado</p>
                </div>
            </div>

            <RecentOrdersPanel class="order-1 md:order-none" :orders="recentOrderCards" :loading="loading" view-all-to="/dashboard/superadmin/pedidos" />
        </section>
    </div>
</template>
