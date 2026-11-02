<script setup lang="ts">
import { CarFront, Check, CircleAlert, Hash, MapPin, Pencil, ShoppingBag, Trash2, UserRound } from '@lucide/vue';
import { useVehicleActions } from '~/composables/useVehicleActions';
import { useRuntimeConfig } from '#imports';
import { getVehicleTypeLabel, getUsageProfileLabel } from '~/utils/vehicleEnums';

interface Vehicle {
    id: number | string;
    plate?: string;
    type?: number | string;
    type_label?: string;
    name?: string;
    register_state?: string;
    register_city?: string;
    usage_profile?: number | string;
    brand?: string;
    model?: string;
    year?: string | number;
    color?: string;
    etiqueta?: string;
    comprada?: boolean;
}

defineProps<{
    vehicle: Vehicle;
}>();

const emit = defineEmits<{
    vehicleDeleted: [id: number | string];
}>();

const { confirmDelete } = useVehicleActions();

const handleDelete = (vehicle: Vehicle) => {
    const label = vehicle.plate ? `placa ${vehicle.plate}` : undefined;
    confirmDelete(vehicle.id, label, () => {
        emit('vehicleDeleted', vehicle.id);
    });
};

// Ajuda a compor URLs de assets respeitando app.baseURL em produção
const runtimeConfig = useRuntimeConfig();
const assetWithBase = (path: string) => {
    const base = runtimeConfig.app?.baseURL || '/';
    const normalizedBase = base.endsWith('/') ? base.slice(0, -1) : base;
    const normalizedPath = path.startsWith('/') ? path : `/${path}`;
    return `${normalizedBase}${normalizedPath}`;
};

// Mapeamento de cores de etiqueta para caminhos de arquivo
const getEtiquetaImagePath = (cor: string | null) => {
    if (!cor) return undefined;
    const colorMap: { [key: string]: string } = {
        'amarela': '/images/dashboard/etiquetas-v2/amarelo.svg',
        'amarelo': '/images/dashboard/etiquetas-v2/amarelo.svg',
        'branco': '/images/dashboard/etiquetas-v2/branco.svg',
        'branca': '/images/dashboard/etiquetas-v2/branco.svg',
        'vermelha': '/images/dashboard/etiquetas-v2/vermelho.svg',
        'azul': '/images/dashboard/etiquetas-v2/azul.svg',
        'verde': '/images/dashboard/etiquetas-v2/verde.svg',
        'vermelho': '/images/dashboard/etiquetas-v2/vermelho.svg',
        'laranja': '/images/dashboard/etiquetas-v2/azul.svg'
    };
    const src = colorMap[cor.toLowerCase()];
    return src ? assetWithBase(src) : undefined;
};

const getVehicleTitle = (vehicle: Vehicle) => {
    if (vehicle.name) {
        return vehicle.name;
    }
    return vehicle.plate || 'Veículo';
};

const getVehicleSubtitle = (vehicle: Vehicle) => {
    const pieces: string[] = [];
    if (vehicle.plate) pieces.push((vehicle.plate)?.toUpperCase());
    if (vehicle.type) {
        const typeLabel = vehicle.type_label || getVehicleTypeLabel(vehicle.type) || String(vehicle.type);
        pieces.push(typeLabel);
    }
    if (vehicle.usage_profile) {
        const usageLabel = getUsageProfileLabel(vehicle.usage_profile) || String(vehicle.usage_profile);
        pieces.push(usageLabel);
    }
    if (vehicle.register_city || vehicle.register_state) {
        const registro = [vehicle.register_city, vehicle.register_state].filter(Boolean).join(' - ');
        pieces.push(registro);
    }
    return pieces.join(' • ');
};

const getVehicleType = (vehicle: Vehicle) => vehicle.type
    ? vehicle.type_label || getVehicleTypeLabel(vehicle.type) || String(vehicle.type)
    : '';

const getUsageProfile = (vehicle: Vehicle) => vehicle.usage_profile
    ? getUsageProfileLabel(vehicle.usage_profile) || String(vehicle.usage_profile)
    : '';

const getRegistrationLocation = (vehicle: Vehicle) => [vehicle.register_city, vehicle.register_state].filter(Boolean).join(' - ');
</script>

<template>
    <article class="rounded-2xl border border-gray-200 bg-white p-5 transition-colors hover:border-[#c7d2fe] hover:bg-[#fafbff]">
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#eef2ff] text-[#1f46ee]">
                <CarFront :size="23" :stroke-width="1.9" aria-hidden="true" />
            </div>
            <div class="min-w-0">
                <h3 class="truncate text-xl! font-semibold text-[#0c0f1a] mb-0!">{{ getVehicleTitle(vehicle) }}</h3>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-600">
                    <span v-if="vehicle.plate" class="inline-flex items-center gap-1.5 font-medium uppercase">
                        <Hash :size="13" :stroke-width="1.9" aria-hidden="true" />
                        {{ vehicle.plate }}
                    </span>
                    <span v-if="getVehicleType(vehicle)" class="inline-flex items-center gap-1.5 capitalize">
                        <CarFront :size="13" :stroke-width="1.9" aria-hidden="true" />
                        {{ getVehicleType(vehicle) }}
                    </span>
                    <span v-if="getUsageProfile(vehicle)" class="inline-flex items-center gap-1.5 capitalize">
                        <UserRound :size="13" :stroke-width="1.9" aria-hidden="true" />
                        {{ getUsageProfile(vehicle) }}
                    </span>
                    <span v-if="getRegistrationLocation(vehicle)" class="inline-flex items-center gap-1.5">
                        <MapPin :size="13" :stroke-width="1.9" aria-hidden="true" />
                        {{ getRegistrationLocation(vehicle) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center md:justify-end">
            <!-- Etiqueta com indicador de compra -->
            <div v-if="vehicle.etiqueta" class="flex flex-col items-center gap-2 relative">
                <img
                    :src="getEtiquetaImagePath(vehicle.etiqueta)"
                    :alt="`Etiqueta ${vehicle.etiqueta}`"
                    class="w-12 h-12 object-contain"
                />
                <!-- Indicador de status -->
                <div
                    :class="[
                        'absolute -bottom-1 -right-1 w-4 h-4 rounded-full flex items-center justify-center text-white text-xs font-bold',
                        vehicle.comprada ? 'bg-green-500' : 'bg-yellow-500'
                    ]"
                >
                    <Check v-if="vehicle.comprada" :size="11" :stroke-width="2.5" aria-hidden="true" />
                    <CircleAlert v-else :size="11" :stroke-width="2.2" aria-hidden="true" />
                </div>
                <p class="text-xs font-medium text-gray-600 capitalize">{{ vehicle.etiqueta }}</p>
            </div>
            <div class="w-px h-12 bg-gray-200 hidden md:block"></div>
            <!-- Botão de compra ou ações -->
            <div class="flex w-full items-center gap-2 md:w-auto">
                <NuxtLink
                    :to="`/dashboard/veiculos/editar/${vehicle.id}`"
                    class="order-3 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition-colors hover:border-[#1f46ee] hover:bg-[#eef2ff] hover:text-[#1f46ee]"
                >
                    <Pencil :size="16" :stroke-width="2" aria-hidden="true" />
                    <span class="hidden sm:block">Editar</span>
                </NuxtLink>
                <button 
                    @click="handleDelete(vehicle)"
                    class="order-4 inline-flex min-h-11 items-center gap-2 rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 transition-colors hover:bg-red-50"
                >
                    <Trash2 :size="16" :stroke-width="2" aria-hidden="true" />
                    <span class="hidden sm:block">Excluir</span>
                </button>
                <NuxtLink
                    to="/dashboard/etiquetas"
                    class="order-1 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#1f46ee] px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#1739d4]"
                >
                    <ShoppingBag :size="16" :stroke-width="2" aria-hidden="true" />
                    {{ vehicle.comprada ? 'Comprar novamente' : 'Comprar Etiqueta' }}
                </NuxtLink>
                <span class="order-2 flex-1 md:hidden" aria-hidden="true"></span>
            </div>
        </div>
        </div>
    </article>
</template>

<style scoped>
@media (max-width: 639px) {
    .order-1,
    .order-3,
    .order-4 {
        min-height: 2.75rem !important;
    }
}
</style>
