<script setup lang="ts">
import { PackageCheck } from '@lucide/vue';
import OrderStatusItem from './OrderStatusItem.vue';

type OrderStatus = 'em-andamento' | 'em-rota' | 'entregue' | 'cancelado';

defineProps<{
    orders: Array<{ id: string; title: string; status: OrderStatus; statusLabel?:string; paymentLabel?:string; fulfillmentLabel?:string }>;
    loading?: boolean;
    viewAllTo: string;
}>();
</script>

<template>
    <section class="dashboard-recent-orders rounded-2xl border border-gray-100 bg-white p-5">
        <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3.5">
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#eef2ff] text-[#1f46ee]">
                    <PackageCheck :size="19" :stroke-width="2" aria-hidden="true" />
                </span>
                <h3 class="relative top-[-9px] mb-0! text-lg! font-semibold text-[#0c0f1a]">Últimos Pedidos</h3>
            </div>
            <NuxtLink
                :to="viewAllTo"
                class="rounded-md px-2 py-1 text-[12.25px] font-semibold text-[#1f46ee] transition-colors hover:bg-[#dbe5ff] hover:text-[#1739d4] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f46ee]"
            >
                Ver todos
            </NuxtLink>
        </div>

        <div v-if="loading" class="space-y-4">
            <div v-for="index in 4" :key="index" class="space-y-2 rounded-xl border border-gray-100 bg-[#fafbff] p-3">
                <Skeleton width="100%" height="16px" />
                <Skeleton width="62%" height="12px" />
                <Skeleton width="100%" height="6px" class="rounded-full" />
            </div>
        </div>

        <div v-else-if="orders.length" class="space-y-2.5">
            <OrderStatusItem
                v-for="order in orders"
                :key="order.id"
                :order-id="order.id"
                :title="order.title"
                :status="order.status"
                :status-label="order.statusLabel"
                :payment-label="order.paymentLabel"
                :fulfillment-label="order.fulfillmentLabel"
            />
        </div>

        <div v-else class="flex min-h-48 flex-col items-center justify-center gap-2 text-center text-gray-500">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#eef2ff] text-[#1f46ee]">
                <PackageCheck :size="22" :stroke-width="1.8" aria-hidden="true" />
            </span>
            <p class="mb-0! text-sm font-semibold text-[#172b4d]">Nenhum pedido encontrado</p>
            <p class="mb-0! text-xs text-gray-500">Os pedidos mais recentes aparecerão aqui.</p>
        </div>
    </section>
</template>
