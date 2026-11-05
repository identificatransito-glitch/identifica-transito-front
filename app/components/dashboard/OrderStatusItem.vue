<script setup>
import { CheckCircle2, Package, Truck, XCircle } from '@lucide/vue';

const props = defineProps({
    orderId: { type: String, required: true },
    title: { type: String, required: true },
    statusLabel: { type: String, default: '' },
    paymentLabel: { type: String, default: '' },
    fulfillmentLabel: { type: String, default: '' },
    status: { type: String, required: true, validator: (value) => ['em-andamento', 'em-rota', 'entregue', 'cancelado'].includes(value) }
});

const statusConfig = {
    'em-andamento': {
        label: 'Em andamento',
        color: 'bg-blue-500',
        textColor: 'text-blue-700',
        bgLight: 'bg-blue-50',
        icon: Package,
        iconClass: 'bg-blue-50 text-blue-700'
    },
    'em-rota': {
        label: 'Em rota de entrega',
        color: 'bg-orange-500',
        textColor: 'text-orange-700',
        bgLight: 'bg-orange-50',
        icon: Truck,
        iconClass: 'bg-orange-50 text-orange-700'
    },
    'entregue': {
        label: 'Entregue',
        color: 'bg-green-500',
        textColor: 'text-green-700',
        bgLight: 'bg-green-50',
        icon: CheckCircle2,
        iconClass: 'bg-green-50 text-green-700'
    },
    'cancelado': {
        label: 'Cancelado',
        color: 'bg-red-500',
        textColor: 'text-red-700',
        bgLight: 'bg-red-50',
        icon: XCircle,
        iconClass: 'bg-red-50 text-red-700'
    }
};

const currentStatus = computed(() => statusConfig[props.status] || statusConfig['em-andamento']);
</script>

<template>
    <article class="dashboard-order-status rounded-xl border border-gray-100 bg-[#fafbff] p-3">
        <div class="flex items-start gap-3">
            <span :class="currentStatus.iconClass" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                <component :is="currentStatus.icon" :size="18" :stroke-width="1.9" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-[#0c0f1a] break-words">{{ props.title }}</p>
                        <p class="mt-0.5 text-xs text-gray-600 break-words">Pedido {{ props.orderId }}</p>
                        <p v-if="props.fulfillmentLabel" class="mt-2 text-sm font-medium text-[#344b71] break-words">{{ props.fulfillmentLabel }}</p>
                        <p v-if="props.paymentLabel" class="mt-1 text-xs text-gray-600 break-words">{{ props.paymentLabel }}</p>
                    </div>
                    <span :class="[currentStatus.textColor, currentStatus.bgLight]" class="w-fit shrink-0 rounded-md px-2 py-1 text-xs font-medium whitespace-nowrap">
                        {{ props.statusLabel || currentStatus.label }}
                    </span>
                </div>
            </div>
        </div>
        <div class="ml-12 mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200">
            <div :class="currentStatus.color" class="h-full transition-all" :style="{ width: props.status === 'entregue' || props.status === 'cancelado' ? '100%' : props.status === 'em-rota' ? '66%' : '33%' }"></div>
        </div>
    </article>
</template>
