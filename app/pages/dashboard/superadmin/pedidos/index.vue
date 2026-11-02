<script setup lang="ts">
import HeroSection from '~/components/dashboard/HeroSection.vue';
import AdminOrdersPage from '~/pages/dashboard/admin/pedidos/index.vue';
import PaginationControls from '~/components/PaginationControls.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import { BadgeCheck, Package } from '@lucide/vue';
import { useToast } from 'primevue/usetoast';

definePageMeta({ layout: 'dashboard' });
useHead({ title: 'Pedidos - SuperAdmin | Identifica Trânsito' });

const { $api } = useNuxtApp();
const toast = useToast();

const config = useRuntimeConfig();
const assetWithBase = (path: string) => {
    if (path.startsWith('data:') || path.startsWith('http')) return path;
    return `${config.app.baseURL}${path}`.replace(/\/+/g, '/').replace(':/', '://');
};

interface Shipment {
    id: number;
    tracking_code: string | null;
    carrier: string;
    carrier_service: string | null;
    status: number;
    status_label: string;
    estimated_delivery_at: string | null;
    delivered_at: string | null;
    last_event: string | null;
    notes: string | null;
}

interface Order {
    id: number;
    vehicle_id: number;
    vehicle_name: string;
    vehicle_plate: string;
    user_id: number;
    user_name: string;
    tag_id: number;
    tag_name: string;
    tag_slug: string | null;
    discount_coupon: number | null;
    coupon_info: { code: string; type: number; value: string } | null;
    qty: number;
    price: string;
    price_total: string;
    shipping_price: string;
    delivery: any | null;
    pickup_point: { id: number; label: string } | null;
    payment_method: string;
    mp_payment_id: string | null;
    mp_payment_status: string | null;
    mp_payment_status_label: string | null;
    boleto_url: string | null;
    status: number;
    shipment: Shipment | null;
    created_at: string;
}

const PAYMENT_STATUS = [
    { label: 'Pendente', value: 0 },
    { label: 'Aprovado', value: 1 },
    { label: 'Cancelado', value: 2 },
    { label: 'Rejeitado', value: 3 },
];

const paymentFilterOptions = [{ label: 'Todos pagamentos', value: 'all' }, ...PAYMENT_STATUS.map((status) => ({ label: status.label, value: String(status.value) }))];

const SHIPMENT_STATUS = [
    { label: 'Aguardando postagem', value: 0 },
    { label: 'Postado', value: 1 },
    { label: 'Em trânsito', value: 2 },
    { label: 'Saiu para entrega', value: 3 },
    { label: 'Entregue', value: 4 },
    { label: 'Tentativa falhou', value: 5 },
    { label: 'Devolvido', value: 6 },
];

const shipmentStatusOptions = SHIPMENT_STATUS.map((status) => ({ label: status.label, value: status.value }));
const shipmentFilterOptions = [{ label: 'Todas entregas', value: 'all' }, ...SHIPMENT_STATUS.map((status) => ({ label: status.label, value: String(status.value) }))];

const PICKUP_STATUS = [
    { label: 'Aguardando', value: 0 },
    { label: 'Disponível para Retirada', value: 1 },
    { label: 'Retirado', value: 4 },
];

const PAYMENT_METHOD_LABEL: Record<string, string> = {
    credit_card: 'Cartão',
    boleto: 'Boleto',
    pix: 'PIX',
    free: 'Gratuito',
};

const paymentStatusClass = (status: number) => ({
    0: 'bg-yellow-100 text-yellow-700',
    1: 'bg-green-100 text-green-700',
    2: 'bg-gray-100 text-gray-500',
    3: 'bg-red-100 text-red-700',
}[status] ?? 'bg-gray-100 text-gray-500');

const shipmentStatusClass = (status: number) => ({
    0: 'bg-gray-100 text-gray-500',
    1: 'bg-blue-100 text-blue-700',
    2: 'bg-blue-100 text-blue-700',
    3: 'bg-indigo-100 text-indigo-700',
    4: 'bg-green-100 text-green-700',
    5: 'bg-orange-100 text-orange-700',
    6: 'bg-red-100 text-red-700',
}[status] ?? 'bg-gray-100 text-gray-500');

const getShipmentStatusLabel = (order: Order) => {
    const status = order.shipment?.status ?? 0;
    const options = order.pickup_point ? PICKUP_STATUS : SHIPMENT_STATUS;
    return options.find((s) => s.value === status)?.label ?? '-';
};

const getOrderDisplayId = (order: Order) => order.mp_payment_id ?? String(order.id);

// ── List ──────────────────────────────────────────────────────────────────────
const orders = ref<Order[]>([]);
const loading = ref(false);
const search = ref('');
const paymentFilter = ref<'all' | string>('all');
const shipmentFilter = ref<'all' | string>('all');
const filtersOpen = ref(false);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0 });

const filteredOrders = computed(() => orders.value.filter((order) =>
    (paymentFilter.value === 'all' || String(order.status) === paymentFilter.value)
    && (shipmentFilter.value === 'all' || String(order.shipment?.status ?? 0) === shipmentFilter.value)
));
const approvedOrders = computed(() => orders.value.filter((order) => order.status === 1).length);

const fetchOrders = async (page = 1) => {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page), per_page: '15' });
        if (search.value) params.set('q', search.value);
        const res = await $api(`/admin/orders?${params}`) as any;
        orders.value = Array.isArray(res?.data) ? res.data : [];
        pagination.value.currentPage = Number(res?.meta?.current_page ?? page);
        pagination.value.lastPage = Number(res?.meta?.last_page ?? 1);
        pagination.value.total = Number(res?.meta?.total ?? orders.value.length);
    } catch (e) {
        console.error('Erro ao carregar pedidos:', e);
    } finally {
        loading.value = false;
    }
};

const goToPage = (page: number) => {
    if (page < 1 || page > pagination.value.lastPage || page === pagination.value.currentPage || loading.value) return;
    fetchOrders(page);
};

const onSearch = () => {
    pagination.value.currentPage = 1;
    fetchOrders(1);
};
const scheduleSearch = () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(onSearch, 280);
};
const clearFilters = () => {
    search.value = '';
    paymentFilter.value = 'all';
    shipmentFilter.value = 'all';
    onSearch();
};

const formatDate = (d: string) => {
    if (!d) return '-';
    if (d.includes('/')) return d.split(' ')[0];
    return new Date(d).toLocaleDateString('pt-BR');
};

const formatCurrency = (v: string | number) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v));

// ── Payment inline edit ───────────────────────────────────────────────────────
const editingPaymentId = ref<number | null>(null);
const editPaymentStatus = ref<number>(0);
const savingPayment = ref(false);

const openPaymentEdit = (order: Order) => {
    editingPaymentId.value = order.id;
    editPaymentStatus.value = order.status;
};

const cancelPaymentEdit = () => { editingPaymentId.value = null; };

const savePaymentStatus = async (order: Order) => {
    savingPayment.value = true;
    try {
        await $api(`/admin/orders/${order.id}`, {
            method: 'PATCH',
            body: { status: editPaymentStatus.value },
        });
        order.status = editPaymentStatus.value;
        editingPaymentId.value = null;
        toast.add({ severity: 'success', summary: 'Sucesso!', detail: 'Status de pagamento atualizado.', life: 3000 });
    } catch (error: any) {
        const msg = error?.data?.message || 'Não foi possível atualizar o status.';
        toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 });
    } finally {
        savingPayment.value = false;
    }
};

// ── Shipment inline edit (pickup) ─────────────────────────────────────────────
const editingShipmentId = ref<number | null>(null);
const editShipmentStatusInline = ref<number>(0);
const savingShipmentInline = ref(false);

const cancelShipmentEdit = () => { editingShipmentId.value = null; };

const saveShipmentInline = async (order: Order) => {
    savingShipmentInline.value = true;
    try {
        await $api(`/admin/orders/${order.id}/shipment`, {
            method: 'PATCH',
            body: { status: editShipmentStatusInline.value },
        });
        if (order.shipment) order.shipment.status = editShipmentStatusInline.value;
        editingShipmentId.value = null;
        toast.add({ severity: 'success', summary: 'Sucesso!', detail: 'Status de entrega atualizado.', life: 3000 });
    } catch (error: any) {
        const msg = error?.data?.message || 'Não foi possível atualizar o status.';
        toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 });
    } finally {
        savingShipmentInline.value = false;
    }
};

// ── Shipment modal (delivery) ─────────────────────────────────────────────────
const showShipmentModal = ref(false);
const modalOrder = ref<Order | null>(null);
const editShipmentStatus = ref<number>(0);
const editTrackingCode = ref<string>('');
const savingShipment = ref(false);

const isPickup = (order: Order) => order.pickup_point !== null;

const openShipmentEdit = (order: Order) => {
    if (isPickup(order)) {
        editingShipmentId.value = order.id;
        editShipmentStatusInline.value = order.shipment?.status ?? 0;
    } else {
        modalOrder.value = order;
        editShipmentStatus.value = order.shipment?.status ?? 0;
        editTrackingCode.value = order.shipment?.tracking_code ?? '';
        showShipmentModal.value = true;
    }
};

const saveShipmentModal = async () => {
    if (!modalOrder.value) return;
    savingShipment.value = true;
    try {
        await $api(`/admin/orders/${modalOrder.value.id}/shipment`, {
            method: 'PATCH',
            body: {
                status: editShipmentStatus.value,
                tracking_code: editTrackingCode.value || null,
            },
        });
        if (modalOrder.value.shipment) {
            modalOrder.value.shipment.status = editShipmentStatus.value;
            modalOrder.value.shipment.tracking_code = editTrackingCode.value || null;
        }
        showShipmentModal.value = false;
        toast.add({ severity: 'success', summary: 'Sucesso!', detail: 'Status de entrega atualizado.', life: 3000 });
    } catch (error: any) {
        const msg = error?.data?.message || 'Não foi possível atualizar o status.';
        toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 });
    } finally {
        savingShipment.value = false;
    }
};

// A listagem visível reutiliza a implementação validada do Admin acima.

// ── Print label (etiqueta de embalagem) ───────────────────────────────────────
const showPrintModal = ref(false);
const printOrder = ref<Order | null>(null);

const openPrintLabel = (order: Order) => {
    printOrder.value = order;
    showPrintModal.value = true;
};

const printLabel = () => {
    window.print();
};
</script>

<template>
    <AdminOrdersPage />
    <div v-if="false" class="admin-page space-y-6 md:space-y-7">
        <HeroSection
            title="Pedidos"
            subtitle="Acompanhe pagamentos, entregas e solicitações em toda a operação."
            greeting="Operação de pedidos"
            :showButton="false"
        />

        <div class="admin-orders-page bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="mb-4 flex flex-col gap-4 border-b border-gray-100 pb-5 lg:flex-row lg:justify-between">
                <div>
                    <h2 class="text-[#172b4d]" style="margin: 0 0 6px; font-size: 25px; font-weight: 600; line-height: 1.1;">Gerenciamento de pedidos</h2>
                    <p class="text-[#52667f]" style="margin: 0; font-size: 15px; line-height: 1.35;">Acompanhe pagamentos, entregas e solicitações de toda a plataforma.</p>
                </div>
                <div class="flex items-center gap-4 self-start text-xs font-semibold uppercase tracking-wide text-[#52667f] lg:self-end">
                    <span class="inline-flex items-center gap-1.5"><Package :size="15" :stroke-width="1.9" class="text-[#1f46ee]" />{{ pagination.total }} pedidos</span>
                    <span class="inline-flex items-center gap-1.5"><BadgeCheck :size="15" :stroke-width="1.9" class="text-[#16803c]" />{{ approvedOrders }} aprovados</span>
                </div>
            </div>
            <button type="button" class="mb-4 flex w-full items-center justify-between border-b border-gray-100 pb-4 text-[15px] font-medium text-[#52667f] md:hidden" :aria-expanded="filtersOpen" @click="filtersOpen = !filtersOpen"><span class="inline-flex items-center gap-2"><i class="pi pi-filter text-xs text-[#8291a7]"></i>Filtros</span><i :class="filtersOpen ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="text-xs text-[#8291a7]"></i></button>
            <div :class="[filtersOpen ? 'max-h-[32rem] mb-6 border-b border-gray-100 pb-5 opacity-100' : 'max-h-0 overflow-hidden opacity-0 pointer-events-none', 'admin-orders-filters grid gap-3 transition-[max-height,opacity,margin,padding] duration-300 ease-in-out md:max-h-none md:overflow-visible md:pointer-events-auto md:mb-6 md:border-b md:border-gray-100 md:pb-5 md:opacity-100 md:grid-cols-2 xl:grid-cols-[minmax(25rem,1.45fr)_minmax(18rem,1fr)_minmax(18rem,1fr)_auto] xl:items-center']">
                <div class="relative min-w-0">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por cliente, placa..."
                        class="admin-orders-search h-12 w-full border border-gray-300 pl-9 pr-4 text-sm focus:outline-none"
                        @input="scheduleSearch"
                    />
                </div>
                <SelectInput v-model="paymentFilter" :options="paymentFilterOptions" show-icon icon="pi pi-credit-card" :icon-offset-y="2" wrapper-class="admin-orders-filter-select" select-class="!h-12 !bg-[#fafafa]" />
                <SelectInput v-model="shipmentFilter" :options="shipmentFilterOptions" show-icon icon="pi pi-truck" :icon-offset-y="2" wrapper-class="admin-orders-filter-select" select-class="!h-12 !bg-[#fafafa]" />
                <button @click="clearFilters" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-[#d8dee8] px-4 text-sm font-semibold text-[#52667f] transition-colors hover:bg-[#f7f9fc]"><i class="pi pi-filter-slash text-xs"></i>Limpar</button>
            </div>

            <!-- Skeleton -->
            <div v-if="loading" class="space-y-3">
                <div v-for="n in 8" :key="n" class="flex gap-4 items-center p-3">
                    <Skeleton width="3%" height="14px" />
                    <Skeleton width="12%" height="14px" />
                    <Skeleton width="18%" height="14px" />
                    <Skeleton width="12%" height="14px" />
                    <Skeleton width="5%" height="14px" />
                    <Skeleton width="10%" height="14px" />
                    <Skeleton width="14%" height="24px" />
                    <Skeleton width="16%" height="24px" />
                    <Skeleton width="8%" height="14px" />
                    <Skeleton width="6%" height="24px" />
                </div>
            </div>

            <!-- Table -->
            <div v-else-if="filteredOrders.length > 0" class="admin-orders-table overflow-x-auto rounded-xl border border-[#e8edf5]">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-gray-700 font-semibold">Cliente</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">ID pedido</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Veículo</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Etiqueta</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">QTD</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Total</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Pagamento</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Entrega</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(order, index) in filteredOrders"
                            :key="order.id"
                            class="border-b border-gray-100 hover:bg-gray-50 transition"
                        >
                            <td class="py-3 px-4 text-gray-700 font-medium">{{ order.user_name }}</td>

                            <td class="py-3 px-4 text-center text-gray-700 font-mono text-xs">{{ getOrderDisplayId(order) }}</td>

                            <td class="py-3 px-4 text-center">
                                <p class="font-medium text-gray-900">{{ order.vehicle_name }}</p>
                                <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 text-xs uppercase">{{ order.vehicle_plate }}</span>
                            </td>

                            <td class="py-3 px-4">
                                <div class="flex items-center justify-center gap-2">
                                    <img
                                        v-if="order.tag_slug"
                                        :src="assetWithBase(`/images/dashboard/etiquetas-v2/${order.tag_slug}.svg`)"
                                        :alt="order.tag_name"
                                        class="w-6 h-6 object-contain"
                                    />
                                    <span class="text-gray-700">{{ order.tag_name }}</span>
                                </div>
                            </td>

                            <td class="py-3 px-4 text-center text-gray-700">{{ order.qty }}</td>

                            <td class="py-3 px-4 text-center">
                                <p class="font-medium text-gray-900">{{ formatCurrency(order.price_total) }}</p>
                                <span v-if="order.coupon_info" class="text-xs text-green-600">
                                    {{ order.coupon_info.code }}
                                </span>
                            </td>

                            <!-- Payment Status -->
                            <td class="py-3 px-4">
                                <div v-if="editingPaymentId === order.id" class="flex flex-col gap-2 min-w-38">
                                    <select
                                        v-model="editPaymentStatus"
                                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:outline-none"
                                    >
                                        <option v-for="opt in PAYMENT_STATUS" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                    <div class="flex gap-1.5">
                                        <button
                                            @click="savePaymentStatus(order)"
                                            :disabled="savingPayment"
                                            class="flex-1 px-2 py-1 bg-it-primary text-white rounded text-xs font-medium hover:bg-blue-700 disabled:opacity-50 transition"
                                        >
                                            <i class="pi pi-check text-xs"></i> Salvar
                                        </button>
                                        <button
                                            @click="cancelPaymentEdit"
                                            class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-600 hover:bg-gray-50 transition"
                                        >
                                            <i class="pi pi-times text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col gap-1">
                                    <button
                                        @click="openPaymentEdit(order)"
                                        class="group flex items-center gap-1.5 w-fit"
                                        title="Clique para editar"
                                    >
                                        <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', paymentStatusClass(order.status)]">
                                            {{ PAYMENT_STATUS.find(s => s.value === order.status)?.label ?? '-' }}
                                        </span>
                                        <i class="pi pi-pencil text-gray-400 text-xs opacity-0 group-hover:opacity-100 transition"></i>
                                    </button>
                                    <span class="text-xs text-gray-400">{{ PAYMENT_METHOD_LABEL[order.payment_method] ?? order.payment_method }}</span>
                                </div>
                            </td>

                            <!-- Shipment Status -->
                            <td class="py-3 px-4">
                                <div v-if="editingShipmentId === order.id" class="flex flex-col gap-2 min-w-38">
                                    <select
                                        v-model="editShipmentStatusInline"
                                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:outline-none"
                                    >
                                        <option v-for="opt in PICKUP_STATUS" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                    <div class="flex gap-1.5">
                                        <button
                                            @click="saveShipmentInline(order)"
                                            :disabled="savingShipmentInline"
                                            class="flex-1 px-2 py-1 bg-it-primary text-white rounded text-xs font-medium hover:bg-blue-700 disabled:opacity-50 transition"
                                        >
                                            <i class="pi pi-check text-xs"></i> Salvar
                                        </button>
                                        <button
                                            @click="cancelShipmentEdit"
                                            class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-600 hover:bg-gray-50 transition"
                                        >
                                            <i class="pi pi-times text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col gap-1">
                                    <button
                                        @click="openShipmentEdit(order)"
                                        class="group flex items-center gap-1.5 w-fit"
                                        title="Clique para editar"
                                    >
                                        <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', shipmentStatusClass(order.shipment?.status ?? 0)]">
                                            {{ getShipmentStatusLabel(order) }}
                                        </span>
                                        <i class="pi pi-pencil text-gray-400 text-xs opacity-0 group-hover:opacity-100 transition"></i>
                                    </button>
                                    <span class="text-xs text-gray-400">
                                        {{ order.pickup_point ? order.pickup_point.label : 'Entrega em domicílio' }}
                                    </span>
                                    <span v-if="order.shipment?.tracking_code" class="text-xs text-blue-500 font-mono">
                                        {{ order.shipment.tracking_code }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-3 px-4 text-center text-gray-500">{{ formatDate(order.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty -->
            <div v-else class="flex flex-col items-center justify-center py-12 gap-2 text-gray-400">
                <i class="pi pi-shopping-cart text-4xl"></i>
                <p>Nenhum pedido encontrado</p>
            </div>

            <!-- Pagination -->
            <div class="mt-5 pt-4 border-t border-gray-100">
                <PaginationControls
                    :current-page="pagination.currentPage"
                    :last-page="pagination.lastPage"
                    :loading="loading"
                    @page-change="goToPage"
                />
            </div>
        </div>

        <!-- Shipment Modal (entrega em domicílio) -->
        <Dialog
            v-model:visible="showShipmentModal"
            header="Atualizar Entrega"
            :modal="true"
            :closable="true"
            class="admin-dialog"
            :style="{ width: '440px' }"
        >
            <div v-if="modalOrder" class="space-y-5 pt-2">
                <div>
                    <p class="text-sm text-gray-500 mb-4">
                        Pedido <span class="font-medium text-gray-700">#{{ modalOrder.id }}</span> —
                        {{ modalOrder.user_name }} · {{ modalOrder.vehicle_name }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-900 mb-2">Status de Entrega</label>
                    <select
                        v-model="editShipmentStatus"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none"
                    >
                        <option v-for="opt in SHIPMENT_STATUS" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-900 mb-2">Código de Rastreio</label>
                    <input
                        v-model="editTrackingCode"
                        type="text"
                        placeholder="Ex: BR123456789BR"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none font-mono"
                    />
                    <p class="text-xs text-gray-400 mt-1">Opcional. Informe o código de rastreio da transportadora.</p>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <button
                        @click="showShipmentModal = false"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="saveShipmentModal"
                        :disabled="savingShipment"
                        class="px-4 py-2 bg-it-primary text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition"
                    >
                        <i v-if="!savingShipment" class="pi pi-check mr-1"></i>
                        <i v-else class="pi pi-spin pi-spinner mr-1"></i>
                        Salvar
                    </button>
                </div>
            </template>
        </Dialog>

        <!-- Print Label Modal (etiqueta para embalagem) -->
        <Dialog
            v-model:visible="showPrintModal"
            header="Etiqueta para Embalagem"
            :modal="true"
            :closable="true"
            class="admin-dialog"
            :style="{ width: '420px' }"
        >
            <div v-if="printOrder" id="print-label-area" class="print-label">
                <div class="print-label__header">
                    <span class="print-label__brand">Identifica Trânsito</span>
                    <span class="print-label__order-id">ID de pagamento: {{ getOrderDisplayId(printOrder) }}</span>
                </div>

                <div class="print-label__tag">
                    <img
                        v-if="printOrder.tag_slug"
                        :src="assetWithBase(`/images/dashboard/etiquetas-v2/${printOrder.tag_slug}.svg`)"
                        :alt="printOrder.tag_name"
                        class="print-label__tag-image"
                    />
                </div>

                <div class="print-label__rows">
                    <div class="print-label__row">
                        <span class="print-label__label">Cliente</span>
                        <span class="print-label__value">{{ printOrder.user_name }}</span>
                    </div>
                    <div class="print-label__row">
                        <span class="print-label__label">Cor da Etiqueta</span>
                        <span class="print-label__value">{{ printOrder.tag_name }}</span>
                    </div>
                    <div class="print-label__row">
                        <span class="print-label__label">Quantidade</span>
                        <span class="print-label__value">{{ printOrder.qty }}</span>
                    </div>
                    <div class="print-label__row">
                        <span class="print-label__label">Valor Pago</span>
                        <span class="print-label__value">{{ formatCurrency(printOrder.price_total) }}</span>
                    </div>
                </div>

                <div class="print-label__footer">
                    {{ formatDate(printOrder.created_at) }}
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <button
                        @click="showPrintModal = false"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition"
                    >
                        Fechar
                    </button>
                    <button
                        @click="printLabel"
                        class="px-4 py-2 bg-it-primary text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition"
                    >
                        <i class="pi pi-print mr-1"></i>
                        Imprimir
                    </button>
                </div>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.print-label {
    border: 2px dashed #cbd5e1;
    border-radius: 1rem;
    padding: 1.5rem;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
}

.print-label__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.75rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.print-label__brand {
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #081ae7;
}

.print-label__order-id {
    font-size: 0.75rem;
    font-family: monospace;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
}

.print-label__tag {
    display: flex;
    justify-content: center;
    padding: 0.5rem 0 1.25rem;
}

.print-label__tag-image {
    width: 5rem;
    height: 5rem;
    object-fit: contain;
}

.print-label__rows {
    display: grid;
    gap: 0.65rem;
}

.print-label__row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
}

.print-label__label {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.print-label__value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    text-align: right;
}

.print-label__footer {
    margin-top: 1.25rem;
    padding-top: 0.75rem;
    border-top: 1px dashed #cbd5e1;
    text-align: center;
    font-size: 0.7rem;
    color: #94a3b8;
}
</style>

<style>
@media print {
    body * {
        visibility: hidden;
    }

    #print-label-area,
    #print-label-area * {
        visibility: visible;
    }

    #print-label-area {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
    }
}
</style>
