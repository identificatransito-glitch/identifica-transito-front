<script setup lang="ts">
import { CarFront, Check, Eye, LockKeyhole, MapPin, UserRound } from '@lucide/vue';

interface Vehicle {
    id: number;
    plate: string;
    name: string;
    register_state: string;
    register_city: string;
    usage_profile_label: string;
    tag_color: string;
    tag_price: string;
    can_purchase_tag: boolean;
    selected?: boolean;
}

const props = defineProps<{
    vehicle: Vehicle;
    getTagImage: (tagColor: string) => string;
}>();

const emit = defineEmits<{
    toggleSelection: [id: number];
}>();

const handleToggleSelection = (id: number) => {
    if (!props.vehicle.can_purchase_tag) return;
    emit('toggleSelection', id);
};

const handleViewDetails = (id: number) => navigateTo(`/dashboard/veiculos/detalhes/${id}`);
</script>

<template>
    <article class="it-tag-card"
        :class="[
            'flex h-full flex-col rounded-xl border p-5 transition-colors',
            vehicle.can_purchase_tag ? 'cursor-pointer' : 'cursor-default',
            vehicle.selected
                ? 'border-[#1f46ee] bg-[#f4f6ff]'
                : vehicle.can_purchase_tag ? 'border-slate-200 bg-white hover:border-[#aebfff] hover:bg-[#fafbff]' : 'border-slate-200 bg-[#fafafa]'
        ]"
        @click="handleToggleSelection(vehicle.id)"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf0ff] text-[#1f46ee]">
                    <CarFront :size="21" :stroke-width="1.9" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-[#172b4d] mb-0!">{{ vehicle.name }}</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-500 uppercase mb-0!">{{ vehicle.plate }}</p>
                </div>
            </div>
            <input
                v-if="vehicle.can_purchase_tag"
                type="checkbox"
                :checked="vehicle.selected"
                class="mt-1 h-4 w-4 cursor-pointer rounded border-slate-300 text-[#1f46ee] focus:ring-[#1f46ee]"
                :aria-label="`Selecionar veículo ${vehicle.plate}`"
                @click.stop="handleToggleSelection(vehicle.id)"
            />
        </div>

        <div class="my-5 flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-[#fafafa] px-3 py-2.5">
            <div class="min-w-0 space-y-1.5 text-sm text-slate-600">
                <p class="flex items-center gap-1.5 truncate mb-0!"><MapPin :size="14" :stroke-width="1.8" aria-hidden="true" /> {{ vehicle.register_city }} · {{ vehicle.register_state }}</p>
                <p class="flex items-center gap-1.5 truncate capitalize mb-0!"><UserRound :size="14" :stroke-width="1.8" aria-hidden="true" /> {{ vehicle.usage_profile_label }}</p>
            </div>
            <img :src="getTagImage(vehicle.tag_color)" :alt="`Etiqueta para ${vehicle.usage_profile_label}`" class="h-12 w-12 shrink-0 object-contain" />
        </div>

        <div class="mt-auto flex items-end justify-between gap-3 border-t border-slate-200 pt-4">
            <div>
                <p class="text-xs font-medium text-slate-500 mb-1">Kit com 2 etiquetas</p>
                <p class="text-lg font-semibold text-[#172b4d] mb-0!">R$ {{ parseFloat(vehicle.tag_price).toFixed(2) }}</p>
            </div>
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 transition-colors hover:border-[#1f46ee] hover:bg-[#eef2ff] hover:text-[#1f46ee]" :aria-label="`Ver detalhes do veículo ${vehicle.plate}`" title="Ver detalhes" @click.stop="handleViewDetails(vehicle.id)">
                <Eye :size="17" :stroke-width="2" aria-hidden="true" />
            </button>
        </div>

        <div v-if="vehicle.selected" class="mt-4 flex items-center gap-2 text-sm font-semibold text-[#1f46ee]">
            <Check :size="16" :stroke-width="2.2" aria-hidden="true" />
            Selecionado para compra
        </div>
        <div v-else-if="!vehicle.can_purchase_tag" class="mt-4 flex items-center gap-2 text-sm font-medium text-slate-500">
            <LockKeyhole :size="15" :stroke-width="1.9" aria-hidden="true" />
            Etiqueta já adquirida para esta placa
        </div>
    </article>
</template>
