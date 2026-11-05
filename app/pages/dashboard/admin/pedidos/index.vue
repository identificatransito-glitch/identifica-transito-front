<script setup lang="ts">
import ShippingLabelDialog from '~/components/orders/ShippingLabelDialog.vue';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import PaginationControls from '~/components/PaginationControls.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import { BadgeCheck, Package, Printer, QrCode } from '@lucide/vue';
import { useToast } from 'primevue/usetoast';

definePageMeta({ layout: 'dashboard' });
const route = useRoute();
const isSuperAdminView = computed(() => route.path.startsWith('/dashboard/superadmin'));
useHead({ title: computed(() => isSuperAdminView.value ? 'Pedidos - SuperAdmin | Identifica Trânsito' : 'Pedidos - Admin | Identifica Trânsito') });

const { $api } = useNuxtApp();
const toast = useToast();
const ordersApiBase = computed(() => isSuperAdminView.value ? '/admin/orders' : '/admin-pdv/orders');

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
    user_photo?: string | null;
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

const paymentFilterOptions = [
    { label: 'Todos pagamentos', value: 'all' },
    ...PAYMENT_STATUS.map((status) => ({ label: status.label, value: String(status.value) }))
];

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

const shipmentFilterOptions = [
    { label: 'Todas entregas', value: 'all' },
    ...SHIPMENT_STATUS.map((status) => ({ label: status.label, value: String(status.value) }))
];

const PICKUP_STATUS = [
    { label: 'Aguardando', value: 0 },
    { label: 'Disponível para Retirada', value: 1 },
    { label: 'Retirado', value: 4 },
];

const editablePickupStatus = computed(() => isSuperAdminView.value ? PICKUP_STATUS : PICKUP_STATUS.filter(option => option.value !== 4));

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
const printOrder = ref<Order | null>(null);
const orders = ref<Order[]>([]);
const loading = ref(false);
const search = ref('');
let searchDebounce: ReturnType<typeof setTimeout> | null = null;
const paymentFilter = ref<'all' | string>('all');
const shipmentFilter = ref<'all' | string>('all');
const filtersOpen = ref(false);
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0 });

const filteredOrders = computed(() => orders.value.filter((order) => {
    const paymentMatches = paymentFilter.value === 'all' || String(order.status) === paymentFilter.value;
    const shipmentMatches = shipmentFilter.value === 'all' || String(order.shipment?.status ?? 0) === shipmentFilter.value;
    return paymentMatches && shipmentMatches;
}));

const approvedOrders = computed(() => orders.value.filter((order) => order.status === 1).length);

const fetchOrders = async (page = 1) => {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page), per_page: '15' });
        if (search.value) params.set('q', search.value);
        const res = await $api(`${ordersApiBase.value}?${params}`) as any;
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

const getUserPhoto = (photo?: string | null) =>
    assetWithBase(photo || '/images/dashboard/avatar.jpg');

// ── Shipment inline edit (pickup) ─────────────────────────────────────────────
const editingShipmentId = ref<number | null>(null);
const editShipmentStatusInline = ref<number>(0);
const savingShipmentInline = ref(false);

const cancelShipmentEdit = () => { editingShipmentId.value = null; };

const saveShipmentInline = async (order: Order) => {
    savingShipmentInline.value = true;
    try {
        await $api(`${ordersApiBase.value}/${order.id}`, {
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
    if (isPickup(order) && !isSuperAdminView.value && order.shipment?.status === 4) {
        toast.add({severity:'info',summary:'Retirada confirmada',detail:'A entrega já foi registrada pelo QR. Este registro não pode ser reaberto por esta ação.',life:5000});
        return;
    }
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
        await $api(`${ordersApiBase.value}/${modalOrder.value.id}`, {
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

onMounted(() => fetchOrders());
</script>

<template>
    <div class="admin-page space-y-6 md:space-y-7">
        <ShippingLabelDialog :order="printOrder" @close="printOrder = null" />
        <HeroSection
            title="Pedidos"
            :subtitle="isSuperAdminView ? 'Acompanhe pagamentos, entregas e atualizações de todos os pedidos da plataforma.' : 'Acompanhe pagamentos, entregas e atualizações dos pedidos vinculados ao seu PDV.'"
            :greeting="isSuperAdminView ? 'Operação global de pedidos' : 'Operação de pedidos'"
            :showButton="false"
        />

        <div class="admin-orders-page bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <a href="/retirada" class="mb-5 inline-flex items-center gap-2 rounded-lg border border-[#dcdafa] bg-[#f1f0ff] px-4 py-3 text-sm font-semibold text-[#2820d9]"><QrCode :size="19" /> Conferir QR de retirada</a>
            <div class="mb-4 flex flex-col gap-4 border-b border-gray-100 pb-5 lg:flex-row lg:justify-between">
                <div>
                    <h2 class="text-[#172b4d]" style="margin: 0 0 6px; font-size: 25px; font-weight: 600; line-height: 1.1;">Gerenciamento de pedidos</h2>
                    <p class="text-[#52667f]" style="margin: 0; font-size: 15px; line-height: 1.35;">Acompanhe pagamentos, entregas e solicitações da plataforma.</p>
                </div>
                <div class="flex items-center gap-4 self-start text-xs font-semibold uppercase tracking-wide text-[#52667f] lg:self-end">
                    <span class="inline-flex items-center gap-1.5"><Package :size="15" :stroke-width="1.9" class="text-[#1f46ee]" aria-hidden="true" /><span>{{ pagination.total }} pedidos</span></span>
                    <span class="inline-flex items-center gap-1.5"><BadgeCheck :size="15" :stroke-width="1.9" class="text-[#16803c]" aria-hidden="true" /><span>{{ approvedOrders }} aprovados</span></span>
                </div>
            </div>

            <button
                type="button"
                class="mb-4 flex w-full items-center justify-between border-b border-gray-100 pb-4 text-[15px] font-medium text-[#52667f] md:hidden"
                :aria-expanded="filtersOpen"
                @click="filtersOpen = !filtersOpen"
            >
                <span class="inline-flex items-center gap-2"><i class="pi pi-filter text-xs text-[#8291a7]"></i>Filtros</span>
                <i :class="filtersOpen ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="text-xs text-[#8291a7]"></i>
            </button>

            <div :class="[
                filtersOpen ? 'max-h-[32rem] mb-6 border-b border-gray-100 pb-5 opacity-100' : 'max-h-0 overflow-hidden opacity-0 pointer-events-none',
                'admin-orders-filters grid gap-3 transition-[max-height,opacity,margin,padding] duration-300 ease-in-out md:max-h-none md:overflow-visible md:pointer-events-auto md:mb-6 md:border-b md:border-gray-100 md:pb-5 md:opacity-100 md:grid-cols-2 xl:grid-cols-[minmax(25rem,1.45fr)_minmax(18rem,1fr)_minmax(18rem,1fr)_auto] xl:items-center'
            ]">
                <div class="relative min-w-0">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por cliente, placa..."
                        class="admin-orders-search h-12 w-full pl-9 pr-4 border border-gray-300 rounded-lg text-sm focus:outline-none"
                        @input="scheduleSearch"
                    />
                </div>
                <SelectInput v-model="paymentFilter" :options="paymentFilterOptions" show-icon icon="pi pi-credit-card" :icon-offset-y="2" wrapper-class="admin-orders-filter-select" select-class="!h-12 !bg-[#fafafa]" />
                <SelectInput v-model="shipmentFilter" :options="shipmentFilterOptions" show-icon icon="pi pi-truck" :icon-offset-y="2" wrapper-class="admin-orders-filter-select" select-class="!h-12 !bg-[#fafafa]" />
                <button @click="clearFilters" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-[#d8dee8] px-4 text-sm font-semibold text-[#52667f] transition-colors hover:bg-[#f7f9fc]">
                    <i class="pi pi-filter-slash text-xs"></i> Limpar
                </button>
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
                </div>
            </div>

            <!-- Table -->
            <template v-else-if="filteredOrders.length > 0">
            <div class="admin-orders-table hidden overflow-x-auto rounded-xl border border-[#e8edf5] md:block">
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
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Imprimir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="order in filteredOrders"
                            :key="order.id"
                            class="border-b border-gray-100 hover:bg-gray-50 transition"
                        >
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2.5">
                                    <img :src="getUserPhoto(order.user_photo)" :alt="`Foto de ${order.user_name}`" class="h-8 w-8 rounded-full object-cover ring-2 ring-white" />
                                    <span class="font-medium text-gray-700">{{ order.user_name }}</span>
                                </div>
                            </td>

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
                            </td>

                            <!-- Payment Status -->
                            <td class="py-3 px-4">
                                <div class="flex w-full flex-col items-center gap-1">
                                    <span :class="['inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium', paymentStatusClass(order.status)]">
                                        {{ PAYMENT_STATUS.find(s => s.value === order.status)?.label ?? '-' }}
                                    </span>
                                    <span class="text-xs font-medium text-[#52667f]">{{ PAYMENT_METHOD_LABEL[order.payment_method] ?? order.payment_method }}</span>
                                </div>
                            </td>

                            <!-- Shipment Status -->
                            <td class="py-3 px-4 text-center">
                                <div v-if="editingShipmentId === order.id" class="mx-auto flex max-w-44 flex-col gap-2 min-w-38">
                                    <select
                                        v-model="editShipmentStatusInline"
                                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:outline-none"
                                    >
                                        <option v-for="opt in editablePickupStatus" :key="opt.value" :value="opt.value">
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
                                <div v-else class="flex w-full flex-col items-center gap-1">
                                    <button
                                        @click="openShipmentEdit(order)"
                                        class="admin-order-shipment-edit flex items-center gap-1.5"
                                        title="Clique para editar"
                                    >
                                        <span :class="['inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium', shipmentStatusClass(order.shipment?.status ?? 0)]">
                                            {{ getShipmentStatusLabel(order) }}
                                        </span>
                                        <i class="pi pi-pencil rounded-md p-1 text-[10px] text-[#94a3b8] transition-colors hover:text-[#1f46ee]"></i>
                                    </button>
                                    <span class="max-w-40 text-center text-xs font-medium leading-snug text-[#52667f]">
                                        {{ order.pickup_point ? order.pickup_point.label : 'Entrega em domicílio' }}
                                    </span>
                                    <span v-if="order.shipment?.tracking_code" class="text-xs text-blue-500 font-mono whitespace-nowrap">
                                        {{ order.shipment.tracking_code }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-3 px-4 text-center text-gray-500 whitespace-nowrap">{{ formatDate(order.created_at) }}</td>
                            <td class="py-3 px-4 text-center"><button type="button" @click="printOrder = order" :aria-label="`Imprimir etiqueta do pedido ${order.id}`" class="inline-flex rounded-lg border border-slate-200 p-2.5 text-[#2820d9] hover:bg-[#f1f0ff]"><Printer :size="19" /></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Cards: mobile -->
            <div class="grid gap-3 md:hidden">
                <article v-for="order in filteredOrders" :key="order.id" class="rounded-2xl border border-[#e4eaf2] bg-white p-4">
                    <header class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#f2f5ff]">
                                <img
                                    v-if="order.tag_slug"
                                    :src="assetWithBase(`/images/dashboard/etiquetas-v2/${order.tag_slug}.svg`)"
                                    :alt="order.tag_name"
                                    class="h-8 w-8 object-contain"
                                />
                                <i v-else class="pi pi-tag text-[#1f46ee]"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="mb-0 truncate text-sm font-bold text-[#172b4d]">{{ order.tag_name }}</p>
                                <p class="mt-0.5 mb-0 font-mono text-[11px] text-[#64748b]">{{ getOrderDisplayId(order) }}</p>
                            </div>
                        </div>
                        <span class="shrink-0 text-xs text-[#64748b]">{{ formatDate(order.created_at) }}</span>
                    </header>

                    <div class="mt-4 flex items-center gap-2.5 border-y border-[#edf1f6] py-3">
                        <img :src="getUserPhoto(order.user_photo)" :alt="`Foto de ${order.user_name}`" class="h-9 w-9 rounded-full object-cover ring-2 ring-white" />
                        <div class="min-w-0">
                            <span class="block text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Cliente</span>
                            <span class="block truncate text-sm font-semibold text-[#172b4d]">{{ order.user_name }}</span>
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 pt-3 text-sm">
                        <div class="min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Veículo</dt>
                            <dd class="mt-0.5 truncate font-semibold text-[#172b4d]">{{ order.vehicle_name }}</dd>
                            <span class="inline-block rounded bg-[#f1f5f9] px-1.5 py-0.5 font-mono text-[10px] text-[#52667f]">{{ order.vehicle_plate }}</span>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Total</dt>
                            <dd class="mt-0.5 font-bold text-[#172b4d]">{{ formatCurrency(order.price_total) }}</dd>
                            <span class="text-xs text-[#64748b]">{{ order.qty }} {{ order.qty === 1 ? 'unidade' : 'unidades' }}</span>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Pagamento</dt>
                            <dd class="mt-1">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', paymentStatusClass(order.status)]">
                                    {{ PAYMENT_STATUS.find(s => s.value === order.status)?.label ?? '-' }}
                                </span>
                            </dd>
                            <span class="mt-1 block text-xs text-[#64748b]">{{ PAYMENT_METHOD_LABEL[order.payment_method] ?? order.payment_method }}</span>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Entrega</dt>
                            <dd v-if="editingShipmentId !== order.id" class="mt-1">
                                <button @click="openShipmentEdit(order)" class="inline-flex max-w-full items-center gap-1.5 text-left" title="Clique para editar">
                                    <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', shipmentStatusClass(order.shipment?.status ?? 0)]">{{ getShipmentStatusLabel(order) }}</span>
                                    <i class="pi pi-pencil text-[10px] text-[#8291a7]"></i>
                                </button>
                            </dd>
                            <div v-else class="mt-1 flex gap-1.5">
                                <select v-model="editShipmentStatusInline" class="min-w-0 flex-1 rounded-lg border border-gray-300 px-2 py-1 text-xs">
                                    <option v-for="opt in editablePickupStatus" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                                <button @click="saveShipmentInline(order)" :disabled="savingShipmentInline" class="rounded-lg bg-it-primary px-2 text-xs text-white"><i class="pi pi-check"></i></button>
                            </div>
                            <span class="mt-1 block truncate text-xs text-[#64748b]">{{ order.pickup_point ? order.pickup_point.label : 'Entrega em domicílio' }}</span>
                        </div>
                    </dl>

                    <p v-if="order.shipment?.tracking_code" class="mt-3 border-t border-[#edf1f6] pt-3 font-mono text-[11px] text-[#1f46ee]">{{ order.shipment.tracking_code }}</p>
                    <button type="button" @click="printOrder = order" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 py-3 text-sm font-semibold text-[#2820d9]"><Printer :size="18" /> Imprimir etiqueta</button>
                </article>
            </div>
            </template>

            <!-- Empty -->
            <div v-else class="flex flex-col items-center justify-center py-12 gap-2 text-gray-400">
                <i class="pi pi-shopping-cart text-4xl"></i>
                <p>Nenhum pedido encontrado</p>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.total >= 26" class="mt-5">
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
                    <SelectInput
                        v-model="editShipmentStatus"
                        :options="shipmentStatusOptions"
                        show-icon
                        icon="pi pi-truck"
                        select-class="!h-12 !bg-[#fafafa]"
                        list-class="admin-orders-sort-panel"
                    />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-900 mb-2">Código de Rastreio</label>
                    <input
                        v-model="editTrackingCode"
                        type="text"
                        placeholder="Ex: BR123456789BR"
                        class="w-full border border-[#cbd5e1] rounded-lg px-3 py-2.5 text-sm font-mono focus:border-[#64748b] focus:outline-none focus:ring-1 focus:ring-[#94a3b8]"
                    />
                    <p class="text-xs text-gray-400 mt-1">Opcional. Informe o código de rastreio da transportadora.</p>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <button
                        @click="showShipmentModal = false"
                        class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#cbd5e1] px-4 text-sm font-semibold text-[#52667f] transition-colors hover:bg-[#f7f9fc]"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="saveShipmentModal"
                        :disabled="savingShipment"
                        class="inline-flex min-h-10 items-center justify-center rounded-lg bg-it-primary px-4 text-sm font-semibold text-white transition-colors hover:bg-[#1f3fd8] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <i v-if="!savingShipment" class="pi pi-check mr-1"></i>
                        <i v-else class="pi pi-spin pi-spinner mr-1"></i>
                        Salvar
                    </button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
