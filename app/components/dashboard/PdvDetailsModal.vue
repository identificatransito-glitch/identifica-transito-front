<script setup lang="ts">
import { formatPdvCommission } from '~/utils/pdvCommission';
import Dialog from 'primevue/dialog';
import { CalendarDays, DollarSign, MapPin, Pencil, Store, Tag, Trash2, X } from '@lucide/vue';

type Point = { access?: { configured: boolean; email: string | null }; commission_percentage?: string | number | null; id: number; name: string; cnpj: string | null; address: string | null; phone: string | null; responsible: string | null; created_at: string };
type Performance = { total_sales_qty?: number; total_sales_amount?: number; commission_amount?: number };
const props = defineProps<{ visible: boolean; point: Point | null; performance?: Performance; loading?: boolean }>();
const emit = defineEmits<{ 'update:visible': [value: boolean]; delete: [point: Point] }>();
const close = () => emit('update:visible', false);
const money = (value: number | undefined) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value ?? 0));
const date = (value: string) => value ? new Date(value).toLocaleDateString('pt-BR') : '-';
const cnpj = (value: string | null) => {
    const digits = (value || '').replace(/\D/g, '');
    return digits ? digits.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5') : '-';
};
</script>

<template>
    <Dialog :visible="visible" modal dismissable-mask :draggable="false" :show-header="false" :pt="{ content: { class: '!p-0' } }" class="w-[calc(100vw-2rem)] max-w-xl !border-0 overflow-hidden" @update:visible="emit('update:visible', $event)">
        <div v-if="point" class="overflow-hidden bg-white">
            <header class="relative bg-it-primary px-5 py-5 text-white">
                <button type="button" class="absolute right-4 top-4 rounded-full p-2 !text-white/80 hover:bg-white/15 hover:!text-white" aria-label="Fechar" @click="close"><X :size="20" /></button>
                <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.16em] !text-white/70">Ponto de venda</p>
                <div class="flex items-start gap-3"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 !text-white"><Store :size="23" /></span><div class="min-w-0"><h2 class="m-0 break-words !text-white" style="font-size:24px !important;font-weight:700 !important;line-height:1.15 !important;">{{ point.name }}</h2><p class="mt-1 flex items-center gap-1.5 text-sm !text-white/75"><MapPin :size="14" class="shrink-0" />{{ point.address || 'Endereço não informado' }}</p></div></div>
            </header>

            <div class="space-y-5 p-5">
                <section>
                    <div class="mb-4"><h3 class="m-0 text-[#172b4d]" style="font-size:18px !important;font-weight:600 !important;line-height:1.25;">Resumo do mês</h3></div>
                    <div v-if="loading" class="grid grid-cols-3 gap-2"><Skeleton v-for="n in 3" :key="n" height="5rem" borderRadius=".75rem" /></div>
                    <div v-else class="grid grid-cols-3 gap-2"><div class="rounded-xl bg-[#f3f6ff] p-3"><DollarSign :size="18" class="mb-4 text-[#64748b]" /><p class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Faturamento</p><p class="mt-1 text-base font-bold text-[#172b4d]">{{ money(performance?.total_sales_amount) }}</p></div><div class="rounded-xl bg-[#f3f6ff] p-3"><Tag :size="18" class="mb-4 text-[#64748b]" /><p class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Etiquetas</p><p class="mt-1 text-base font-bold text-[#172b4d]">{{ performance?.total_sales_qty ?? 0 }}</p></div><div class="rounded-xl bg-[#f3f6ff] p-3"><CalendarDays :size="18" class="mb-4 text-[#64748b]" /><p class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Repasse</p><p class="mt-1 text-base font-bold text-[#172b4d]">{{ money(performance?.commission_amount) }}</p></div></div>
                </section>

                <section class="border-y border-[#e9eef6] py-1">
                    <div class="grid grid-cols-[7rem_1fr] gap-x-3 border-b border-[#edf1f6] py-3 text-sm"><span class="text-[#7284a5]">Acesso do PDV</span><strong class="break-words text-[#172b4d]">{{ point.access?.configured ? point.access.email : 'Configurar na edição' }}</strong></div>
                    <div class="grid grid-cols-[7rem_1fr] gap-x-3 border-b border-[#edf1f6] py-3 text-sm"><span class="text-[#7284a5]">Repasse do PDV</span><strong class="text-[#172b4d]">{{ formatPdvCommission(point.commission_percentage) }}</strong></div>
                    <div class="grid grid-cols-[7rem_1fr] gap-x-3 border-b border-[#edf1f6] py-3 text-sm"><span class="text-[#7284a5]">CNPJ</span><strong class="font-mono text-[#172b4d]">{{ cnpj(point.cnpj) }}</strong></div>
                    <div class="grid grid-cols-[7rem_1fr] gap-x-3 border-b border-[#edf1f6] py-3 text-sm"><span class="text-[#7284a5]">Cadastro</span><strong class="text-[#172b4d]">{{ date(point.created_at) }}</strong></div>
                    <div class="grid grid-cols-[7rem_1fr] gap-x-3 border-b border-[#edf1f6] py-3 text-sm"><span class="text-[#7284a5]">Telefone</span><strong class="text-[#172b4d]">{{ point.phone || '-' }}</strong></div>
                    <div class="grid grid-cols-[7rem_1fr] gap-x-3 py-3 text-sm"><span class="text-[#7284a5]">Responsável</span><strong class="text-[#172b4d]">{{ point.responsible || '-' }}</strong></div>
                </section>

                <footer class="flex gap-3"><NuxtLink :to="`/dashboard/superadmin/pontos-de-venda/${point.id}/editar`" class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-it-primary py-3 text-sm font-semibold text-white"><Pencil :size="16" />Editar</NuxtLink><button class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-red-300 py-3 text-sm font-semibold text-red-500 hover:bg-red-50" @click="emit('delete', point)"><Trash2 :size="16" />Excluir</button></footer>
            </div>
        </div>
    </Dialog>
</template>
