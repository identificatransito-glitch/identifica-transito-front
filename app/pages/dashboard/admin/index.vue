<script setup lang="ts">
import HeroSection from '~/components/dashboard/HeroSection.vue';
import StatsCard from '~/components/dashboard/StatsCard.vue';
import RecentOrdersPanel from '~/components/dashboard/RecentOrdersPanel.vue';
import DashboardPeriodFilter from '~/components/dashboard/DashboardPeriodFilter.vue';
import ManagementCharts from '~/components/dashboard/ManagementCharts.vue';
import { CarFront, CircleDollarSign, HandCoins, PackageCheck, UsersRound } from '@lucide/vue';

definePageMeta({ layout: 'dashboard' });

useHead({
    title: 'Dashboard Admin - Identifica Trânsito',
    meta: [{ name: 'description', content: 'Painel administrativo geral.' }]
});

const { $api } = useNuxtApp();

// Date filter — default: first day of year → today
const today = new Date();
const firstOfYear = new Date(today.getFullYear(), 0, 1);
const dateFrom = ref(firstOfYear.toISOString().split('T')[0]);
const dateTo = ref(today.toISOString().split('T')[0]);

// Interfaces
interface Totals {
    users: number;
    vehicles: number;
    orders: number;
    revenue: number;
    commission_amount: number;
    commission_base_amount?: number;
    sold_qty?: number;
}

interface LatestUser {
    id: number;
    name: string;
    email: string;
    phone: string;
    type: number;
    photo?: string | null;
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

// State
const loading = ref(false);
const dashboardData = ref<DashboardData | null>(null);
const periodFilterRef = ref<{ open: (event: Event) => void } | null>(null);

const totals = computed(() => dashboardData.value?.totals ?? { users: 0, vehicles: 0, orders: 0, revenue: 0, commission_amount: 0 });
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
        const res = await $api(`/admin-pdv/dashboard?date_from=${dateFrom.value}&date_to=${dateTo.value}`) as any;
        dashboardData.value = res?.data ?? res;
    } catch (e) {
        console.error('Erro ao carregar dashboard:', e);
    } finally {
        loading.value = false;
    }
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

const paymentMethodLabel = (method: string) => {
    const map: Record<string, string> = {
        credit_card: 'Cartão de Crédito',
        debit_card: 'Cartão de Débito',
        pix: 'Pix',
        boleto: 'Boleto',
        free: 'Gratuito',
    };
    return map[method] ?? method;
};

const paymentStatusBadge = (status: string) => {
    const map: Record<string, { label: string; classes: string }> = {
        approved: { label: 'Aprovado', classes: 'bg-green-50 text-green-700' },
        pending: { label: 'Pendente', classes: 'bg-yellow-50 text-yellow-700' },
        rejected: { label: 'Rejeitado', classes: 'bg-red-50 text-red-700' },
        cancelled: { label: 'Cancelado', classes: 'bg-gray-100 text-gray-600' },
    };
    return map[status] ?? { label: status, classes: 'bg-gray-100 text-gray-600' };
};

const typeLabel = (type: number | string) => {
    if (Number(type) === 1) return 'SuperAdmin';
    if (Number(type) === 2) return 'Admin';
    return 'Cliente';
};

onMounted(fetchDashboard);
</script>

<template>
    <div class="admin-page space-y-6 md:space-y-7">
        <HeroSection
            title="Dashboard"
            subtitle="Acompanhe os resultados e a operação do seu ponto de venda em um só lugar."
            greeting="Visão do seu PDV"
            :showButton="false"
            :show-filter-button="true"
            @filter="periodFilterRef?.open($event)"
        />

        <DashboardPeriodFilter
            ref="periodFilterRef"
            v-model:date-from="dateFrom"
            v-model:date-to="dateTo"
            :loading="loading"
            @apply="fetchDashboard"
        />

        <!-- Stats Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-5">
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
            <StatsCard
                title="Valor a Repassar"
                :value="formatCurrency(totals.commission_amount)"
                :icon="HandCoins"
                color="teal"
                :loading="loading"
            />
        </section>

        <section class="rounded-xl border border-[#dce2ff] bg-[#f3f5ff] p-4 text-sm text-[#52667f]" aria-label="Regra de repasse">
            <p><strong>{{ totals.sold_qty ?? 0 }} kit(s) aprovado(s)</strong> · Base do repasse: <strong>{{ formatCurrency(totals.commission_base_amount ?? 0) }}</strong>.</p>
            <p class="mt-1">O repasse considera o valor dos kits antes dos descontos, sem frete, inclusive em compras com cupom de 100%. Na retirada, recebe o PDV escolhido na compra.</p>
        </section>

        <ManagementCharts
            :latest-orders="latestOrders"
            :tag-sales="tagSales"
            :balance-history="balanceHistory"
            :date-from="dateFrom"
            :date-to="dateTo"
            :loading="loading"
        />

        <!-- Bottom panels -->
        <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <!-- Recent Users Table -->
            <div class="order-2 xl:order-none xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-3.5">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#eef2ff] text-[#1f46ee]">
                            <UsersRound :size="19" :stroke-width="2" aria-hidden="true" />
                        </span>
                        <h3 class="relative top-[-9px] mb-0! text-lg! font-semibold text-[#0c0f1a]">Últimos Usuários</h3>
                    </div>
                    <NuxtLink to="/dashboard/admin/usuarios" class="rounded-md px-2 py-1 text-[12.25px] font-semibold text-[#1f46ee] transition-colors hover:bg-[#dbe5ff] hover:text-[#1739d4]">Ver todos</NuxtLink>
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
                                <tr v-for="u in latestUsers" :key="u.id" class="border-b border-[#edf1f5] last:border-b-0 hover:bg-[#f7f9ff] transition-colors">
                                    <td class="px-4 py-3 font-semibold text-[#172b4d]">{{ u.name }}</td>
                                    <td class="break-words px-4 py-3 text-center text-[#52667f]">{{ u.email }}</td>
                                    <td class="px-4 py-3 text-center"><span class="rounded-full bg-[#f1f5f9] px-2 py-0.5 text-xs font-medium text-[#64748b]">{{ typeLabel(u.type) }}</span></td>
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
                                <span class="shrink-0 rounded-full bg-[#f1f5f9] px-2 py-1 text-xs font-medium text-[#64748b]">{{ typeLabel(u.type) }}</span>
                            </header>
                            <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-[#edf1f6] pt-3 text-center text-xs text-[#64748b]"><div><dt class="mb-1 text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Tipo</dt><dd class="m-0 font-semibold text-[#172b4d]">{{ typeLabel(u.type) }}</dd></div><div><dt class="mb-1 text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Cadastro</dt><dd class="m-0 font-semibold text-[#172b4d]">{{ formatDate(u.created_at) }}</dd></div></dl>
                        </article>
                    </div>
                </template>

                <div v-else class="flex flex-col items-center justify-center py-8 gap-2 text-gray-400">
                    <i class="pi pi-users text-3xl"></i>
                    <p class="text-sm">Nenhum usuário encontrado</p>
                </div>
            </div>

            <RecentOrdersPanel class="order-1 xl:order-none" :orders="recentOrderCards" :loading="loading" view-all-to="/dashboard/admin/pedidos" />
        </section>
    </div>
</template>
