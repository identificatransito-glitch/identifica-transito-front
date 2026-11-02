<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount, onMounted } from 'vue';
import { Check, CircleAlert, ClipboardList, Clock3, CreditCard, ExternalLink, MapPin, PackageCheck, QrCode, ReceiptText, Search, ShoppingBag, SlidersHorizontal, Truck, X } from '@lucide/vue';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import Button from '~/components/forms/Button.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';

definePageMeta({
    layout: 'dashboard'
});

useHead({
    title: 'Meus Pedidos - Identifica Trânsito',
    meta: [
        { name: 'description', content: 'Acompanhe todos os seus pedidos de etiquetas.' }
    ]
});

interface Shipment {
    id?: number;
    status?: number | string | null;
    tracking_code: string;
    carrier: string;
    status_label: string;
    estimated_delivery_at: string | null;
    delivered_at: string | null;
    last_event: string | null;
}

interface ShipmentStatusTotal {
    key: number;
    name: string;
    value: number;
}

interface Order {
    id: number;
    vehicle_id: number;
    tag_name: string;
    tag_slug: string;
    qty: number;
    price: string;
    price_total: string;
    shipping_price: string;
    delivery: Record<string, any> | null;
    pickup_point: {
        id: number;
        name: string;
        cnpj: string;
        phone: string;
        responsible?: string | null;
        responsable?: string | null;
        address?: string | null;
        map_link?: string | null;
    } | null;
    payment_method: string;
    discount_coupon?: number | null;
    coupon_info?: { code: string; type: number; value: string } | null;
    boleto_url: string | null;
    mp_payment_id: string;
    mp_payment_status: string;
    mp_payment_status_label: string;
    status: number | string;
    vehicle: {
        id: number;
        name: string;
        plate_number: string;
        color: string;
        year: string;
        tag_slug?: string | null;
    } | null;
    shipment: Shipment | null;
    created_at: string;
}

const shipmentStatusAliasMap: Record<string, string> = {
    '0': '0',
    aguardando: '0',
    awaiting: '0',
    pending: '0',
    '1': '1',
    postado: '1',
    posted: '1',
    shipped: '1',
    '2': '2',
    'em trânsito': '2',
    'em transito': '2',
    in_transit: '2',
    '3': '3',
    'saiu para entrega': '3',
    out_for_delivery: '3',
    '4': '4',
    entregue: '4',
    delivered: '4',
    '5': '5',
    'tentativa falhou': '5',
    failed_attempt: '5',
    failed: '5',
    '6': '6',
    devolvido: '6',
    returned: '6'
};

const normalizeShipmentStatus = (status: number | string | null | undefined) => {
    const statusKey = String(status ?? '').toLowerCase();
    return shipmentStatusAliasMap[statusKey] ?? statusKey;
};

const getOrderShipmentStatus = (order: Order) => {
    const shipmentStatus = normalizeShipmentStatus(order.shipment?.status ?? order.shipment?.status_label);

    if (order.pickup_point && shipmentStatus === '1') return 'pickup_available';
    if (order.pickup_point && shipmentStatus === '4') return 'pickup_completed';

    if (shipmentStatus) return shipmentStatus;
    if (order.pickup_point) return 'pickup';
    return '0';
};

// Filtros e estado
const selectedStatus = ref('todos');
const searchQuery = ref('');
const showFilters = ref(false);
const draftStatus = ref(selectedStatus.value);
const draftSearchQuery = ref(searchQuery.value);
const filterToggle = ref<HTMLButtonElement | null>(null);
const filterPopover = ref<HTMLElement | null>(null);

const openFilters = () => {
    draftStatus.value = selectedStatus.value;
    draftSearchQuery.value = searchQuery.value;
    showFilters.value = true;
};

const closeFilters = () => {
    showFilters.value = false;
};

const applyFilters = () => {
    selectedStatus.value = draftStatus.value;
    searchQuery.value = draftSearchQuery.value;
    closeFilters();
};

const clearDraftFilters = () => {
    draftStatus.value = 'todos';
    draftSearchQuery.value = '';
};

const closeFiltersOnOutsideClick = (event: MouseEvent) => {
    if (!showFilters.value) return;

    const target = event.target;
    const isSelectOverlay = target instanceof Element && Boolean(target.closest('.p-select-overlay'));

    if (!isSelectOverlay && target instanceof Node && !filterPopover.value?.contains(target) && !filterToggle.value?.contains(target)) {
        closeFilters();
    }
};

onMounted(() => document.addEventListener('mousedown', closeFiltersOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', closeFiltersOnOutsideClick));
const confirm = useConfirm();
const toast = useToast();

// Dados dos pedidos via API
const allOrders = ref<Order[]>([]);
const loadingOrders = ref(false);
const shipmentTotals = ref<Record<string, number>>({});
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0
});

const fetchShipmentTotals = async () => {
    try {
        const { $api } = useNuxtApp();
        const response = await $api('/shipments/totals-by-status') as any;
        const data = response?.data || response;
        const list = Array.isArray(data) ? data : data?.data || [];

        shipmentTotals.value = (list as ShipmentStatusTotal[]).reduce((acc, item) => {
            acc[String(item.key)] = Number(item.value || 0);
            return acc;
        }, {} as Record<string, number>);
    } catch (error) {
        console.error('Erro ao carregar totais por status de envio:', error);
        shipmentTotals.value = {};
    }
};

const getShipmentTotal = (keys: Array<number | string>) => {
    return keys.reduce((total, key) => total + (shipmentTotals.value[String(key)] || 0), 0);
};

const totalShipmentCount = computed(() => {
    return Object.values(shipmentTotals.value).reduce((sum, value) => sum + value, 0);
});

const selectedStatusToQuery: Record<string, string | null> = {
    todos: null,
    pending: '0',
    processing: '1',
    shipped: '2,3',
    delivered: '4',
    cancelled: '5,6'
};

const fetchOrders = async (page = 1) => {
    loadingOrders.value = true;
    try {
        const { $api } = useNuxtApp();
        const query = new URLSearchParams();
        query.set('page', String(page));

        const searchValue = searchQuery.value.trim();
        if (searchValue) {
            query.set('q', searchValue);
        }

        const statusValue = selectedStatusToQuery[selectedStatus.value] ?? null;
        if (statusValue) {
            query.set('status', statusValue);
        }

        const response = await $api(`/orders?${query.toString()}`) as any;

        const payload = response?.data?.data ? response.data : response;
        const data = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
        allOrders.value = data;

        const meta = payload?.meta ?? payload;
        pagination.value.currentPage = Number(meta?.current_page ?? 1);
        pagination.value.lastPage = Number(meta?.last_page ?? 1);
        pagination.value.total = Number(meta?.total ?? data.length ?? 0);
    } catch (error) {
        console.error('Erro ao carregar pedidos:', error);
    } finally {
        loadingOrders.value = false;
    }
};

const goToPage = (page: number) => {
    if (page < 1 || page > pagination.value.lastPage || page === pagination.value.currentPage || loadingOrders.value) {
        return;
    }

    fetchOrders(page);
};

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, () => {
    if (searchDebounce) {
        clearTimeout(searchDebounce);
    }

    searchDebounce = setTimeout(() => {
        fetchOrders(1);
    }, 400);
});

watch(selectedStatus, () => {
    fetchOrders(1);
});


// Configuração de status
const statusConfig: Record<string, { label: string; color: string; textColor: string; bgLight: string; darkBgLight: string; darkTextColor: string; icon: string }> = {
    '0': {
        label: 'Aguardando',
        color: 'bg-yellow-500',
        textColor: 'text-yellow-700',
        bgLight: 'bg-yellow-50',
        darkBgLight: 'dark:bg-yellow-900/20',
        darkTextColor: 'dark:text-yellow-300',
        icon: 'pi pi-clock'
    },
    '1': {
        label: 'Postado',
        color: 'bg-blue-500',
        textColor: 'text-blue-700',
        bgLight: 'bg-blue-50',
        darkBgLight: 'dark:bg-blue-900/20',
        darkTextColor: 'dark:text-blue-300',
        icon: 'pi pi-box'
    },
    '2': {
        label: 'Em trânsito',
        color: 'bg-orange-500',
        textColor: 'text-orange-700',
        bgLight: 'bg-orange-50',
        darkBgLight: 'dark:bg-orange-900/20',
        darkTextColor: 'dark:text-orange-300',
        icon: 'pi pi-truck'
    },
    '3': {
        label: 'Saiu para entrega',
        color: 'bg-indigo-500',
        textColor: 'text-indigo-700',
        bgLight: 'bg-indigo-50',
        darkBgLight: 'dark:bg-indigo-900/20',
        darkTextColor: 'dark:text-indigo-300',
        icon: 'pi pi-send'
    },
    '4': {
        label: 'Entregue',
        color: 'bg-green-500',
        textColor: 'text-green-700',
        bgLight: 'bg-green-50',
        darkBgLight: 'dark:bg-green-900/20',
        darkTextColor: 'dark:text-green-300',
        icon: 'pi pi-check-circle'
    },
    '5': {
        label: 'Tentativa falhou',
        color: 'bg-red-500',
        textColor: 'text-red-700',
        bgLight: 'bg-red-50',
        darkBgLight: 'dark:bg-red-900/20',
        darkTextColor: 'dark:text-red-300',
        icon: 'pi pi-times-circle'
    },
    '6': {
        label: 'Devolvido',
        color: 'bg-rose-500',
        textColor: 'text-rose-700',
        bgLight: 'bg-rose-50',
        darkBgLight: 'dark:bg-rose-900/20',
        darkTextColor: 'dark:text-rose-300',
        icon: 'pi pi-replay'
    },
    pickup: {
        label: 'Retirada no ponto',
        color: 'bg-purple-500',
        textColor: 'text-purple-700',
        bgLight: 'bg-purple-50',
        darkBgLight: 'dark:bg-purple-900/20',
        darkTextColor: 'dark:text-purple-300',
        icon: 'pi pi-map-marker'
    },
    pickup_available: {
        label: 'Retirada Disponível',
        color: 'bg-purple-500',
        textColor: 'text-purple-700',
        bgLight: 'bg-purple-50',
        darkBgLight: 'dark:bg-purple-900/20',
        darkTextColor: 'dark:text-purple-300',
        icon: 'pi pi-map-marker'
    },
    pickup_completed: {
        label: 'Retirado',
        color: 'bg-green-500',
        textColor: 'text-green-700',
        bgLight: 'bg-green-50',
        darkBgLight: 'dark:bg-green-900/20',
        darkTextColor: 'dark:text-green-300',
        icon: 'pi pi-check-circle'
    }
};

const defaultStatusConfig = {
    label: 'Desconhecido',
    color: 'bg-gray-400',
    textColor: 'text-gray-700',
    bgLight: 'bg-gray-50',
    darkBgLight: 'dark:bg-gray-900/20',
    darkTextColor: 'dark:text-gray-300',
    icon: 'pi pi-circle'
};

const getStatusConfig = (order: Order) => statusConfig[getOrderShipmentStatus(order)] ?? defaultStatusConfig;
const getStatusIcon = (order: Order) => {
    const status = getOrderShipmentStatus(order);
    if (['4', 'pickup_completed'].includes(status)) return Check;
    if (['2', '3'].includes(status)) return Truck;
    if (['5', '6'].includes(status)) return CircleAlert;
    return Clock3;
};

const paymentStatusConfig: Record<string, { bgLight: string; textColor: string; darkBgLight: string; darkTextColor: string }> = {
    approved: {
        bgLight: 'bg-emerald-50',
        textColor: 'text-emerald-700',
        darkBgLight: 'dark:bg-emerald-900/20',
        darkTextColor: 'dark:text-emerald-300'
    },
    pending: {
        bgLight: 'bg-amber-50',
        textColor: 'text-amber-700',
        darkBgLight: 'dark:bg-amber-900/20',
        darkTextColor: 'dark:text-amber-300'
    },
    cancelled: {
        bgLight: 'bg-rose-50',
        textColor: 'text-rose-700',
        darkBgLight: 'dark:bg-rose-900/20',
        darkTextColor: 'dark:text-rose-300'
    },
    rejected: {
        bgLight: 'bg-red-50',
        textColor: 'text-red-700',
        darkBgLight: 'dark:bg-red-900/20',
        darkTextColor: 'dark:text-red-300'
    },
    in_process: {
        bgLight: 'bg-sky-50',
        textColor: 'text-sky-700',
        darkBgLight: 'dark:bg-sky-900/20',
        darkTextColor: 'dark:text-sky-300'
    },
    refunded: {
        bgLight: 'bg-violet-50',
        textColor: 'text-violet-700',
        darkBgLight: 'dark:bg-violet-900/20',
        darkTextColor: 'dark:text-violet-300'
    },
    in_mediation: {
        bgLight: 'bg-orange-50',
        textColor: 'text-orange-700',
        darkBgLight: 'dark:bg-orange-900/20',
        darkTextColor: 'dark:text-orange-300'
    },
    charged_back: {
        bgLight: 'bg-fuchsia-50',
        textColor: 'text-fuchsia-700',
        darkBgLight: 'dark:bg-fuchsia-900/20',
        darkTextColor: 'dark:text-fuchsia-300'
    }
};

const defaultPaymentStatusConfig = {
    bgLight: 'bg-gray-100',
    textColor: 'text-gray-600',
    darkBgLight: 'dark:bg-surface-700',
    darkTextColor: 'dark:text-gray-300'
};

const getPaymentStatusConfig = (status: string) => paymentStatusConfig[status] ?? defaultPaymentStatusConfig;
const getOrderPaymentStatus = (order: Order) => String(order.mp_payment_status || '').toLowerCase();
const isPaymentCancelled = (order: Order) => {
    const paymentStatus = getOrderPaymentStatus(order);
    const paymentStatusLabel = String(order.mp_payment_status_label || '').toLowerCase();

    return ['cancelled', 'canceled', 'cancelado'].includes(paymentStatus)
        || paymentStatusLabel.includes('cancel');
};

// Progresso por status
const statusProgress: Record<string, number> = {
    '0': 10,
    '1': 30,
    '2': 55,
    '3': 80,
    '4': 100,
    '5': 100,
    '6': 100,
    pickup: 100,
    pickup_available: 50,
    pickup_completed: 100
};

const getStatusProgress = (order: Order) => statusProgress[getOrderShipmentStatus(order)] ?? 10;

const isDeliveredStatus = (order: Order) => ['4', 'pickup_completed'].includes(getOrderShipmentStatus(order));
const isAwaitingShipmentStatus = (order: Order) => getOrderShipmentStatus(order) === '0';

// Filtros de status
const statusFilters = computed(() => [
    { value: 'todos', label: 'Todos', count: totalShipmentCount.value },
    { value: 'pending', label: 'Aguardando', count: getShipmentTotal([0]) },
    { value: 'processing', label: 'Postado', count: getShipmentTotal([1]) },
    { value: 'shipped', label: 'Em trânsito', count: getShipmentTotal([2, 3]) },
    { value: 'delivered', label: 'Entregue', count: getShipmentTotal([4]) },
    { value: 'cancelled', label: 'Falhou/Devolvido', count: getShipmentTotal([5, 6]) }
]);

// Pedidos filtrados
const filteredOrders = computed(() => {
    const statusGroups: Record<string, string[]> = {
        pending: ['0'],
        processing: ['1'],
        shipped: ['2', '3'],
        delivered: ['4'],
        cancelled: ['5', '6']
    };
    const query = searchQuery.value.trim().toLowerCase();

    return allOrders.value.filter((order) => {
        const statusMatches = selectedStatus.value === 'todos' || statusGroups[selectedStatus.value]?.includes(getOrderShipmentStatus(order));
        const searchMatches = !query || [
            order.mp_payment_id,
            order.vehicle?.name,
            order.vehicle?.plate_number,
            order.tag_name
        ].some((value) => String(value ?? '').toLowerCase().includes(query));

        return Boolean(statusMatches && searchMatches);
    });
});

// Agrupa pedidos pelo mesmo mp_payment_id (compras feitas juntas)
interface OrderGroup {
    mp_payment_id: string;
    items: Order[];
    totalPrice: number;
    first: Order;
}

const groupedOrders = computed((): OrderGroup[] => {
    const map = new Map<string, OrderGroup>();
    for (const order of filteredOrders.value) {
        const key = String(order.mp_payment_id);
        if (!map.has(key)) {
            map.set(key, { mp_payment_id: key, items: [], totalPrice: 0, first: order });
        }
        const group = map.get(key)!;
        group.items.push(order);
        group.totalPrice += parseFloat(order.price_total);
    }
    return Array.from(map.values());
});

// Estatísticas via API
const stats = ref({ total: 0, in_progress: 0, completed: 0, failed: 0 });
const loadingStats = ref(false);
const orderHeroStats = computed(() => ({
    inProgress: { label: 'Em andamento', count: stats.value.in_progress },
    completed: { label: 'Concluídos', count: stats.value.completed }
}));

const fetchResume = async () => {
    loadingStats.value = true;
    try {
        const { $api } = useNuxtApp();
        const response = await $api('/orders/resume') as any;
        const resume = response?.data || response || {};
        stats.value = {
            total: Number(resume.total || 0),
            in_progress: Number(resume.in_progress ?? 0) + Number(resume.pending ?? 0) + Number(resume.processing ?? 0),
            completed: Number(resume.completed ?? resume.delivered ?? 0),
            failed: Number(resume.failed ?? resume.cancelled ?? 0)
        };
    } catch (error) {
        console.error('Erro ao carregar resumo de pedidos:', error);
    } finally {
        loadingStats.value = false;
    }
};

onMounted(() => {
    fetchResume();
    fetchShipmentTotals();
    fetchOrders();
});

const formatCurrency = (value: string | number) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(Number(value));
};

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('pt-BR');
};

const openTracking = (code: string) => {
    window.open(`https://rastreamento.correios.com.br/app/index.php?codigo=${code}`, '_blank');
};

const deliveringShipmentIds = ref<number[]>([]);

const refreshMainData = async () => {
    await Promise.all([
        fetchResume(),
        fetchShipmentTotals(),
        fetchOrders(pagination.value.currentPage)
    ]);
};

const confirmReceiveProduct = (shipmentId?: number) => {
    if (!shipmentId) return;

    confirm.require({
        message: 'Confirma que você recebeu este produto?',
        header: 'Confirmar Recebimento',
        icon: 'pi pi-check-circle',
        dismissableMask: true,
        rejectLabel: 'Não',
        acceptLabel: 'Sim, recebi',
        rejectClass: 'p-button-danger',
        acceptClass: 'p-button-success',
        accept: async () => {
            if (deliveringShipmentIds.value.includes(shipmentId)) return;

            deliveringShipmentIds.value.push(shipmentId);
            try {
                const { $api } = useNuxtApp();
                await $api(`/shipments/${shipmentId}/deliver`, { method: 'PATCH' });

                toast.add({
                    severity: 'success',
                    summary: 'Recebimento confirmado',
                    detail: 'Produto marcado como recebido com sucesso.',
                    life: 3500
                });

                await refreshMainData();
            } catch (error: any) {
                const apiMessage = error?.data?.message || error?.data?.error;
                toast.add({
                    severity: 'error',
                    summary: 'Erro ao confirmar recebimento',
                    detail: apiMessage || 'Não foi possível confirmar o recebimento do produto.',
                    life: 5000
                });
            } finally {
                deliveringShipmentIds.value = deliveringShipmentIds.value.filter((id) => id !== shipmentId);
            }
        }
    });
};

const formatCouponDiscount = (group: OrderGroup, couponInfo: { code: string; type: number; value: string }) => {
    const couponItems = group.items.filter(item => item.coupon_info?.code === couponInfo.code);
    if (couponInfo.type === 2) {
        const totalDiscount = couponItems.reduce((sum, item) => sum + Number(couponInfo.value) * item.qty, 0);
        return formatCurrency(totalDiscount);
    }
    const pct = Number(couponInfo.value);
    if (pct >= 100) {
        const originalTotal = couponItems.reduce((sum, item) => sum + Number(item.price) * item.qty, 0);
        return formatCurrency(originalTotal);
    }
    return `${pct}%`;
};

const config = useRuntimeConfig();
const assetWithBase = (path: string) => {
    if (path.startsWith('data:') || path.startsWith('http')) return path;
    return `${config.app.baseURL}${path}`.replace(/\/+/g, '/').replace(':/', '://');
};

const getTagImage = (tagSlug?: string | null) => {
    const path = tagSlug ? `/images/dashboard/etiquetas-v2/${tagSlug}.svg` : '/images/dashboard/etiquetas-v2/amarelo.svg';
    return assetWithBase(path);
};

const displayTypePayment = (type: string | undefined) => {
    if (!type) return 'Desconhecido';

    const typeKey = type.toLowerCase();
    if (typeKey === 'credit_card') return 'Cartão de Crédito';
    if (typeKey === 'boleto') return 'Boleto';
    if (typeKey === 'pix') return 'Pix';
    if (typeKey === 'free') return 'Grátis';
    return type;
};

const getPaymentIcon = (type: string | undefined) => {
    const icons = {
        credit_card: CreditCard,
        pix: QrCode,
        boleto: ReceiptText
    };
    return icons[type?.toLowerCase() as keyof typeof icons] ?? CreditCard;
};
</script>

<template>
    <div class="space-y-6 md:space-y-7">
        <section v-if="false">
        <!-- Hero Section -->
        <HeroSection
            title="Meus Pedidos"
            subtitle="Acompanhe o status de todos os seus pedidos de etiquetas"
            greeting="Rastreamento"
            :greeting-icon="ClipboardList"
            :showStats="true"
            :stats="orderHeroStats"
            :showButton="true"
            buttonLabel="Fazer Novo Pedido"
            buttonLink="/dashboard/veiculos"
            buttonIcon="pi pi-plus"
        />

        <!-- Estatísticas -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <template v-if="loadingStats">
                <div
                    v-for="n in 4"
                    :key="`stats-skeleton-${n}`"
                    class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-6 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="space-y-2 flex-1">
                            <Skeleton width="7rem" height="1rem" />
                            <Skeleton width="4rem" height="2rem" />
                        </div>
                        <Skeleton width="3rem" height="3rem" class="rounded-xl" />
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total de Pedidos</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                            <i class="pi pi-shopping-cart text-xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Pgto. em Andamento</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.in_progress }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center">
                            <i class="pi pi-clock text-xl text-orange-600 dark:text-orange-400"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Pgto. Concluídos</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.completed }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                            <i class="pi pi-check-circle text-xl text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Pgto. Cancelados</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.failed }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                            <i class="pi pi-times-circle text-xl text-red-600 dark:text-red-400"></i>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Filtros e Busca -->
        <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 shadow-sm p-6 space-y-4">
            <!-- Filtros de Status -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="filter in statusFilters"
                    :key="filter.value"
                    @click="selectedStatus = filter.value"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                        selectedStatus === filter.value
                            ? 'bg-it-primary text-white shadow-sm'
                            : 'bg-gray-100 dark:bg-surface-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-surface-600'
                    ]"
                >
                    {{ filter.label }}
                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs" :class="selectedStatus === filter.value ? 'bg-white/20' : 'bg-gray-200 dark:bg-surface-600'">
                        {{ filter.count }}
                    </span>
                </button>
            </div>

            <!-- Busca -->
            <IconField iconPosition="left" class="w-full md:w-80">
                <InputIcon class="pi pi-search" />
                <InputText
                    v-model="searchQuery"
                    type="text"
                    placeholder="Buscar por ID ou Placa..."
                    class="w-full"
                />
            </IconField>
        </div>

        <!-- Loading -->
        <div v-if="loadingOrders" class="space-y-4">
            <div v-for="n in 3" :key="n" class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 shadow-sm p-6">
                <Skeleton width="100%" height="120px" class="rounded-lg" />
            </div>
        </div>

        <!-- Lista de Pedidos -->
        <div v-else-if="groupedOrders.length > 0" class="space-y-4">
            <div
                v-for="group in groupedOrders"
                :key="group.mp_payment_id"
                class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 shadow-sm hover:shadow-md transition-shadow overflow-hidden"
            >
                <!-- Cabeçalho do pedido -->
                <div class="p-6 border-b border-gray-100 dark:border-surface-700">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div :class="[getStatusConfig(group.first).bgLight, getStatusConfig(group.first).darkBgLight, 'w-12 h-12 rounded-xl flex items-center justify-center']">
                                <i :class="[getStatusConfig(group.first).icon, getStatusConfig(group.first).textColor, getStatusConfig(group.first).darkTextColor, 'text-xl']"></i>
                            </div>
                            <div>
                                <div class="flex lg:items-center flex-col lg:flex-row gap-3 mb-1">
                                    <h3 class="text-lg! mb-1! font-bold text-gray-900 dark:text-white">#PED-{{ group.mp_payment_id }}</h3>
                                    <div class="flex gap-3">
                                        <span
                                            v-if="!isPaymentCancelled(group.first)"
                                            :class="[
                                                getStatusConfig(group.first).textColor,
                                                getStatusConfig(group.first).darkTextColor,
                                                getStatusConfig(group.first).bgLight,
                                                getStatusConfig(group.first).darkBgLight,
                                                'px-3 py-1 rounded-full text-xs font-medium'
                                            ]"
                                            v-tooltip.top="'Entrega/Retirada'"
                                        >
                                            {{ getStatusConfig(group.first).label }}
                                        </span>
                                        <span
                                            v-if="group.first.mp_payment_status_label"
                                            :class="[
                                                getPaymentStatusConfig(getOrderPaymentStatus(group.first)).bgLight,
                                                getPaymentStatusConfig(getOrderPaymentStatus(group.first)).textColor,
                                                getPaymentStatusConfig(getOrderPaymentStatus(group.first)).darkBgLight,
                                                getPaymentStatusConfig(getOrderPaymentStatus(group.first)).darkTextColor,
                                                'px-3 py-1 rounded-full text-xs font-medium'
                                            ]"
                                            v-tooltip.top="'Pagamento'"
                                        >
                                            {{ group.first.mp_payment_status_label }}
                                        </span>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pedido realizado em {{ group.first.created_at }}</p>
                            </div>
                        </div>
                        <div class="text-right flex flex-col items-end gap-2">
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(group.totalPrice) }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ displayTypePayment(group.first.payment_method) }}</p>
                            <div
                                v-if="group.first.coupon_info"
                                class="flex items-center gap-1.5 px-2.5 py-1 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-xs text-green-700 dark:text-green-300"
                            >
                                <i class="pi pi-tag text-xs"></i>
                                <span class="font-mono font-semibold uppercase">{{ group.first.coupon_info.code }}</span>
                                <span v-if="Number(group.first.coupon_info.value) > 0" class="text-green-600 dark:text-green-400">
                                    &minus;{{ formatCouponDiscount(group, group.first.coupon_info) }}
                                </span>
                            </div>
                            <a
                                v-if="group.first.payment_method === 'boleto' && group.first.boleto_url"
                                :href="group.first.boleto_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-it-primary text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition"
                            >
                                <i class="pi pi-external-link text-xs"></i>
                                Abrir boleto
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Corpo do pedido -->
                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Itens do pedido -->
                        <div class="lg:col-span-2 space-y-3">
                            <h4 class="text-sm! font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                Itens do Pedido <span class="text-gray-400 font-normal">({{ group.items.length }})</span>
                            </h4>

                            <div
                                v-for="item in group.items"
                                :key="item.id"
                                class="flex lg:items-center justify-between flex-col lg:flex-row p-3 bg-gray-50 dark:bg-surface-700 rounded-lg gap-3"
                            >
                                <div class="flex items-center gap-3">
                                    <img
                                        :src="getTagImage(item.tag_slug)"
                                        :alt="item.tag_name"
                                        class="w-10 h-10 object-contain shrink-0"
                                    />
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ item.tag_name }}
                                            <span v-if="item.qty > 1" class="ml-1 text-xs text-gray-500">(x{{ item.qty }})</span>
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ item.vehicle?.name || 'Veículo não informado' }}
                                            <span v-if="item.vehicle?.plate_number"> · {{ item.vehicle.plate_number.toUpperCase() }}</span>
                                        </p>
                                    </div>
                                </div>
                                <p class="font-semibold text-gray-900 dark:text-white whitespace-nowrap">{{ formatCurrency(item.price) }}</p>
                            </div>

                            <!-- Pickup Point resumido -->
                            <div v-if="group.first.pickup_point" class="p-3 bg-gray-50 dark:bg-surface-700 rounded-lg flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center shrink-0">
                                    <i class="pi pi-map-marker text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Ponto de retirada</p>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ group.first.pickup_point.name }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Informações de entrega/rastreamento -->
                        <div v-if="group.first.delivery || group.first.pickup_point" class="space-y-4">
                            <h4 v-if="group.first.shipment?.tracking_code || group.first.pickup_point" class="text-sm! font-semibold text-gray-700 dark:text-gray-300">Informações de Entrega</h4>

                            <div v-if="group.first.pickup_point" class="p-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-900 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <i class="pi pi-map-marker text-purple-600 dark:text-purple-400 mt-0.5"></i>
                                            <p v-if="group.first.pickup_point.address" class="text-xs text-purple-700 dark:text-purple-300 mt-1">
                                                {{ group.first.pickup_point.address }}
                                            </p>
                                        </div>
                                        <a
                                            v-if="group.first.pickup_point.map_link"
                                            :href="group.first.pickup_point.map_link"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-2 mt-2 text-xs font-medium text-purple-700 dark:text-purple-300 hover:underline"
                                        >
                                            <i class="pi pi-map"></i>
                                            Ver no mapa
                                        </a>
                                        <p v-if="group.first.pickup_point.responsible || group.first.pickup_point.responsable" class="text-xs text-purple-700 dark:text-purple-300 mt-1 flex gap-2">
                                            <i class="pi pi-user"></i>
                                            <strong>Responsável:</strong> {{ group.first.pickup_point.responsible || group.first.pickup_point.responsable }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Código de rastreamento -->
                            <div v-if="group.first.shipment?.tracking_code" class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <i class="pi pi-map-marker text-blue-600 dark:text-blue-400 mt-0.5"></i>
                                    <div class="flex-1">
                                        <p class="text-xs text-blue-600 dark:text-blue-400 font-medium mb-1">Código de Rastreamento</p>
                                        <p class="font-mono text-sm text-blue-900 dark:text-blue-300 font-semibold">{{ group.first.shipment.tracking_code }}</p>
                                        <p v-if="group.first.shipment.carrier" class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ group.first.shipment.carrier }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Data de entrega -->
                            <div v-if="group.first.shipment?.delivered_at" class="flex items-center gap-2 text-sm">
                                <i class="pi pi-calendar text-green-600 dark:text-green-400"></i>
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Entregue em</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ formatDate(group.first.shipment.delivered_at) }}</p>
                                </div>
                            </div>

                            <!-- Previsão de entrega -->
                            <div v-else-if="group.first.shipment?.estimated_delivery_at" class="flex items-center gap-2 text-sm">
                                <i class="pi pi-calendar text-orange-600 dark:text-orange-400"></i>
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Previsão de entrega</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ formatDate(group.first.shipment.estimated_delivery_at) }}</p>
                                </div>
                            </div>

                            <!-- Botão de rastreamento -->
                            <Button
                                v-if="group.first.shipment?.tracking_code && !isDeliveredStatus(group.first)"
                                label="Rastrear Pedido"
                                icon="pi pi-external-link"
                                size="sm"
                                class="w-full"
                                @click="openTracking(group.first.shipment.tracking_code)"
                            />

                            <button
                                v-if="group.first.shipment?.id && !group.first.pickup_point && !isDeliveredStatus(group.first) && !isAwaitingShipmentStatus(group.first)"
                                type="button"
                                class="w-full mt-2 px-3 py-2 rounded-lg border border-blue-200 dark:border-blue-900/50 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-sm font-medium hover:bg-blue-100 dark:hover:bg-blue-900/30 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="deliveringShipmentIds.includes(group.first.shipment.id)"
                                @click="confirmReceiveProduct(group.first.shipment.id)"
                            >
                                <i class="pi pi-check mr-2"></i>
                                Recebi meu Produto
                            </button>
                        </div>
                    </div>

                    <!-- Barra de progresso -->
                    <div v-if="!isPaymentCancelled(group.first)" class="mt-6 pt-6 border-t border-gray-100 dark:border-surface-700">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Status do Pedido</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ getStatusProgress(group.first) }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 dark:bg-surface-700 overflow-hidden">
                            <div
                                :class="getStatusConfig(group.first).color"
                                class="h-full transition-all duration-500"
                                :style="{ width: `${getStatusProgress(group.first)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <PaginationControls
                :current-page="pagination.currentPage"
                :last-page="pagination.lastPage"
                :loading="loadingOrders"
                @page-change="goToPage"
            />
        </div>

        <!-- Estado vazio -->
        <div v-else-if="!loadingOrders" class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 shadow-sm p-12">
            <div class="flex flex-col items-center justify-center text-center space-y-4">
                <div class="w-20 h-20 rounded-full bg-gray-100 dark:bg-surface-700 flex items-center justify-center">
                    <i class="pi pi-inbox text-4xl text-gray-400 dark:text-gray-500"></i>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                        {{ searchQuery ? 'Nenhum pedido encontrado' : 'Nenhum pedido nesta categoria' }}
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ searchQuery ? 'Tente ajustar sua busca ou filtros.' : 'Faça seu primeiro pedido de etiquetas agora mesmo.' }}
                    </p>
                </div>
                <Button
                    v-if="!searchQuery"
                    label="Fazer Novo Pedido"
                    icon="pi pi-plus"
                    @click="navigateTo('/dashboard/veiculos')"
                />
            </div>
        </div>
        </section>

        <HeroSection
            greeting="Rastreamento"
            :greeting-icon="ClipboardList"
            title="Meus pedidos"
            subtitle="Acompanhe o status e a entrega de todas as suas etiquetas."
            :showStats="true"
            :stats="orderHeroStats"
            :showButton="false"
        />

        <section v-if="false" class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,0.75fr)_minmax(0,1.25fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#1f46ee]">Visão geral</p>
                <h2 class="mt-3 text-2xl! font-semibold leading-tight text-[#172b4d] mb-2!">Seu acompanhamento começa aqui.</h2>
                <p class="max-w-md text-sm leading-6 text-slate-600 mb-0!">Consulte o andamento das entregas, localize pedidos e acompanhe cada etapa até a chegada das etiquetas.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
            <article v-for="item in [
                { label: 'Total de pedidos', value: stats.total, icon: ShoppingBag },
                { label: 'Em andamento', value: stats.in_progress, icon: Clock3 },
                { label: 'Concluídos', value: stats.completed, icon: PackageCheck },
                { label: 'Com pendência', value: stats.failed, icon: CircleAlert }
            ]" :key="item.label" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
                <div>
                    <Skeleton v-if="loadingStats" width="2.5rem" height="1.5rem" />
                    <p v-else class="text-2xl font-semibold leading-none text-[#172b4d] mb-1">{{ item.value }}</p>
                    <p class="text-xs font-medium text-slate-500 mb-0!">{{ item.label }}</p>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#eaf0ff] text-[#1f46ee]"><component :is="item.icon" :size="17" :stroke-width="1.9" aria-hidden="true" /></div>
            </article>
            </div>
        </section>

        <section class="mb-7 flex items-center justify-between gap-3">
            <h2 class="relative top-2 text-2xl! font-semibold text-[#172b4d] mb-0!">Pedidos realizados</h2>
            <div class="relative z-20 shrink-0">
                <button ref="filterToggle" type="button" :class="showFilters ? 'bg-[#1739d4]' : 'bg-[#1f46ee]'" class="inline-flex !h-[42px] !min-h-[42px] !w-[42px] items-center justify-center rounded-lg text-white transition-colors hover:bg-[#1739d4] sm:!h-12 sm:!min-h-12 sm:!w-auto sm:gap-2 sm:px-5 sm:text-base sm:font-semibold" aria-label="Filtrar pedidos" @click="showFilters ? closeFilters() : openFilters()">
                    <SlidersHorizontal :size="17" :stroke-width="2" aria-hidden="true" />
                    <span class="hidden sm:inline">Filtrar pedidos</span>
                </button>

                <Transition name="filter-popover">
                    <section v-if="showFilters" ref="filterPopover" class="absolute right-0 top-full mt-2 w-[min(19rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-slate-200 bg-white" role="dialog" aria-modal="false" aria-labelledby="filter-popover-title">
                        <header class="flex items-center justify-between border-b border-slate-200 px-3.5 py-3">
                            <h3 id="filter-popover-title" class="orders-filter-title relative -top-0.5 font-semibold leading-none text-[#172b4d] mb-0!">Filtrar pedidos</h3>
                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-[#172b4d]" aria-label="Fechar filtros" @click="closeFilters"><X :size="18" :stroke-width="2" aria-hidden="true" /></button>
                        </header>
                        <div class="space-y-4 px-4 py-4">
                            <SelectInput
                                v-model="draftStatus"
                                label="Status do pedido"
                                :options="statusFilters.map((filter) => ({ label: `${filter.label} (${filter.count})`, value: filter.value }))"
                                list-class="orders-status-options"
                                label-class="mb-2 block text-sm font-semibold text-[#172b4d]"
                                select-class="!h-11 !rounded-lg !border-slate-300 !bg-[#fafafa] !text-sm !font-medium focus:!border-slate-500 focus:!ring-1 focus:!ring-slate-400"
                            />
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold text-[#172b4d]">Buscar pedido</span>
                                <span class="relative block"><Search :size="17" :stroke-width="1.8" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" /><input v-model="draftSearchQuery" type="search" class="h-11 w-full rounded-lg border border-slate-300 bg-[#fafafa] pl-11 pr-3.5 text-sm font-medium text-[#172b4d] outline-none placeholder:font-normal placeholder:text-slate-400 focus:border-slate-500 focus:ring-1 focus:ring-slate-400" placeholder="Número do pedido ou placa" /></span>
                            </label>
                        </div>
                        <footer class="grid grid-cols-2 gap-2 border-t border-slate-200 px-3.5 py-3">
                            <button type="button" class="inline-flex h-11 w-full items-center justify-center rounded-full border border-slate-300 bg-white px-3 text-sm font-semibold text-[#172b4d] transition-colors hover:border-slate-400 hover:bg-slate-50" @click="clearDraftFilters">Limpar</button>
                            <button type="button" class="inline-flex h-11 w-full items-center justify-center rounded-full bg-[#1f46ee] px-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#1739d4]" @click="applyFilters">Aplicar</button>
                        </footer>
                    </section>
                </Transition>
            </div>
        </section>

        <section v-if="false" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <label class="text-sm font-semibold text-[#172b4d]" for="order-status-filter">Status</label>
                <select id="order-status-filter" v-model="selectedStatus" class="h-11 min-w-52 rounded-lg border border-slate-300 bg-[#fafafa] px-3 text-sm font-medium text-[#172b4d] outline-none focus:border-slate-500 focus:ring-1 focus:ring-slate-400">
                    <option v-for="filter in statusFilters" :key="filter.value" :value="filter.value">{{ filter.label }} ({{ filter.count }})</option>
                </select>
            </div>
            <label class="relative w-full sm:w-80">
                <Search :size="18" :stroke-width="1.8" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                <input v-model="searchQuery" type="search" class="h-11 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-4 text-sm text-[#172b4d] outline-none transition-colors placeholder:text-slate-400 focus:border-slate-500 focus:ring-1 focus:ring-slate-400" placeholder="Buscar por pedido ou placa" />
            </label>
        </section>

        <div v-if="loadingOrders" class="space-y-4">
            <Skeleton v-for="index in 3" :key="index" height="13rem" class="w-full rounded-xl" />
        </div>

        <section v-else-if="groupedOrders.length" class="space-y-4">
            <article v-for="group in groupedOrders" :key="group.mp_payment_id" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <header class="flex flex-col gap-4 border-b border-slate-200 bg-[#fafafa] p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf0ff] text-[#1f46ee]">
                            <component :is="getStatusIcon(group.first)" :size="20" :stroke-width="1.9" aria-hidden="true" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg! font-semibold text-[#172b4d] mb-0!">Pedido #{{ group.mp_payment_id }}</h3>
                                <span v-if="!isPaymentCancelled(group.first)" :class="[getStatusConfig(group.first).bgLight, getStatusConfig(group.first).textColor]" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ getStatusConfig(group.first).label }}</span>
                                <span v-if="group.first.mp_payment_status_label" :class="[getPaymentStatusConfig(getOrderPaymentStatus(group.first)).bgLight, getPaymentStatusConfig(getOrderPaymentStatus(group.first)).textColor]" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ group.first.mp_payment_status_label }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500 mb-0!">Realizado em {{ formatDate(group.first.created_at) }} · {{ group.items.length }} {{ group.items.length === 1 ? 'item' : 'itens' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-4 lg:block lg:text-right">
                        <div>
                            <p class="text-lg font-semibold text-[#172b4d] mb-1">{{ formatCurrency(group.totalPrice) }}</p>
                            <p class="flex items-center justify-end gap-1.5 text-sm text-slate-500 mb-0!"><component :is="getPaymentIcon(group.first.payment_method)" :size="14" :stroke-width="1.8" aria-hidden="true" />{{ displayTypePayment(group.first.payment_method) }}</p>
                        </div>
                    </div>
                </header>

                <div class="grid grid-cols-1 gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <div>
                        <h4 class="mb-3 text-sm! font-semibold text-[#172b4d]">Itens do pedido</h4>
                        <div class="space-y-2">
                            <div v-for="item in group.items" :key="item.id" class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-[#fafafa] p-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <img :src="getTagImage(item.tag_slug)" :alt="item.tag_name" class="h-10 w-10 shrink-0 object-contain" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-[#172b4d] mb-0!">{{ item.tag_name }}<span v-if="item.qty > 1" class="ml-1 text-slate-500">×{{ item.qty }}</span></p>
                                        <p class="mt-0.5 truncate text-sm text-slate-500 mb-0!">{{ item.vehicle?.name || 'Veículo não informado' }}<span v-if="item.vehicle?.plate_number"> · {{ item.vehicle.plate_number.toUpperCase() }}</span></p>
                                    </div>
                                </div>
                                <p class="shrink-0 text-sm font-semibold text-[#172b4d] mb-0!">{{ formatCurrency(item.price) }}</p>
                            </div>
                        </div>
                    </div>

                    <aside class="rounded-xl border border-slate-200 bg-[#fafafa] p-4">
                        <div v-if="group.first.pickup_point" class="mb-4">
                            <p class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-[#172b4d]"><MapPin :size="15" :stroke-width="1.9" aria-hidden="true" /> Retirada</p>
                            <p class="text-sm text-slate-600 mb-1">{{ group.first.pickup_point.name }}</p>
                            <p v-if="group.first.pickup_point.address" class="text-sm text-slate-500 mb-0!">{{ group.first.pickup_point.address }}</p>
                        </div>
                        <div v-else-if="group.first.shipment" class="mb-4">
                            <p class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-[#172b4d]"><Truck :size="15" :stroke-width="1.9" aria-hidden="true" /> Entrega</p>
                            <p v-if="group.first.shipment.tracking_code" class="font-mono text-sm font-semibold text-[#172b4d] mb-1">{{ group.first.shipment.tracking_code }}</p>
                            <p v-if="group.first.shipment.estimated_delivery_at" class="text-sm text-slate-500 mb-0!">Previsão: {{ formatDate(group.first.shipment.estimated_delivery_at) }}</p>
                            <p v-else-if="group.first.shipment.delivered_at" class="text-sm text-slate-500 mb-0!">Entregue em {{ formatDate(group.first.shipment.delivered_at) }}</p>
                        </div>
                        <div v-else class="mb-4">
                            <p class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-[#172b4d]"><Clock3 :size="15" :stroke-width="1.9" aria-hidden="true" /> Aguardando</p>
                            <p class="text-sm text-slate-500 mb-0!">Informações de entrega serão exibidas quando o pedido for preparado.</p>
                        </div>

                        <button v-if="group.first.shipment?.tracking_code && !isDeliveredStatus(group.first)" type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-[#172b4d] transition-colors hover:border-[#1f46ee] hover:bg-[#eef2ff]" @click="openTracking(group.first.shipment.tracking_code)">
                            <ExternalLink :size="16" :stroke-width="2" aria-hidden="true" /> Rastrear pedido
                        </button>
                        <button v-if="group.first.shipment?.id && !group.first.pickup_point && !isDeliveredStatus(group.first) && !isAwaitingShipmentStatus(group.first)" type="button" class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#1f46ee] px-3 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#1739d4] disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="deliveringShipmentIds.includes(group.first.shipment.id)" @click="confirmReceiveProduct(group.first.shipment.id)">
                            <Check :size="16" :stroke-width="2.2" aria-hidden="true" /> Recebi meu pedido
                        </button>
                    </aside>
                </div>

                <div v-if="!isPaymentCancelled(group.first)" class="border-t border-slate-200 px-5 py-4 sm:px-6">
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-600">Andamento do pedido</span>
                        <span class="font-semibold text-[#172b4d]">{{ getStatusProgress(group.first) }}%</span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-[#1f46ee] transition-all duration-500" :style="{ width: `${getStatusProgress(group.first)}%` }"></div></div>
                </div>
            </article>

            <PaginationControls :current-page="pagination.currentPage" :last-page="pagination.lastPage" :loading="loadingOrders" @page-change="goToPage" />
        </section>

        <section v-else class="flex flex-col items-center justify-center rounded-2xl border border-slate-200 bg-white p-8 text-center sm:p-12">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#eaf0ff] text-[#1f46ee]"><ShoppingBag :size="30" :stroke-width="1.8" aria-hidden="true" /></div>
            <h2 class="text-2xl! font-semibold text-[#172b4d] mb-2!">{{ searchQuery ? 'Nenhum pedido encontrado' : 'Você ainda não tem pedidos' }}</h2>
            <p class="max-w-md text-slate-600">{{ searchQuery ? 'Tente ajustar a busca ou os filtros selecionados.' : 'Escolha um veículo e compre seu primeiro kit de etiquetas.' }}</p>
            <NuxtLink v-if="!searchQuery" to="/dashboard/veiculos" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-[#1f46ee] px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#1739d4]"><PackageCheck :size="18" :stroke-width="2.1" aria-hidden="true" /> Ver meus veículos</NuxtLink>
        </section>

        <Teleport v-if="false" to="body">
            <div v-if="showFilters" class="fixed inset-0 z-[1100] flex items-center justify-center p-4 sm:p-6" role="presentation">
                <button type="button" class="absolute inset-0 bg-slate-950/30 backdrop-blur-[1px]" aria-label="Fechar filtros" @click="showFilters = false"></button>
                <section role="dialog" aria-modal="true" aria-labelledby="filter-modal-title" class="relative z-1 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#eaf0ff] text-[#1f46ee]">
                                <SlidersHorizontal :size="19" :stroke-width="1.9" aria-hidden="true" />
                            </div>
                            <h2 id="filter-modal-title" class="relative -top-1 text-xl! font-semibold leading-none text-[#172b4d] mb-0!">Filtrar pedidos</h2>
                        </div>
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-[#172b4d]" aria-label="Fechar filtros" @click="showFilters = false">
                            <X :size="19" :stroke-width="2" aria-hidden="true" />
                        </button>
                    </header>

                    <div class="space-y-5 px-5 py-5">
                        <label class="block">
                            <span class="mb-2 block text-sm font-semibold text-[#172b4d]">Status do pedido</span>
                            <select v-model="selectedStatus" class="h-12 w-full rounded-lg border border-slate-300 bg-[#fafafa] px-3 text-sm font-medium text-[#172b4d] outline-none focus:border-slate-500 focus:ring-1 focus:ring-slate-400">
                                <option v-for="filter in statusFilters" :key="filter.value" :value="filter.value">{{ filter.label }} ({{ filter.count }})</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-semibold text-[#172b4d]">Buscar pedido</span>
                            <span class="relative block">
                                <Search :size="18" :stroke-width="1.8" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input v-model="searchQuery" type="search" class="h-12 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-4 text-sm text-[#172b4d] outline-none transition-colors placeholder:text-slate-400 focus:border-slate-500 focus:ring-1 focus:ring-slate-400" placeholder="Número do pedido ou placa" />
                            </span>
                        </label>
                    </div>

                    <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 px-5 py-4 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100" @click="selectedStatus = 'todos'; searchQuery = ''">Limpar filtros</button>
                        <button type="button" class="rounded-lg bg-[#1f46ee] px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#1739d4]" @click="showFilters = false">Aplicar filtros</button>
                    </footer>
                </section>
            </div>
        </Teleport>
    </div>
</template>

<style>
.orders-filter-title {
    font-size: 1rem !important;
}

.filter-popover-enter-active,
.filter-popover-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.filter-popover-enter-from,
.filter-popover-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.orders-status-options {
    max-height: none !important;
    overflow: visible !important;
    scrollbar-width: none;
}

.orders-status-options::-webkit-scrollbar {
    width: 4px;
}

.orders-status-options::-webkit-scrollbar-track {
    background: transparent;
}

.orders-status-options::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #aeb8c7;
}
</style>
