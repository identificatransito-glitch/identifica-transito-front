<script setup lang="ts">
import { formatPdvCommission } from '~/utils/pdvCommission';
import PaginationControls from '~/components/PaginationControls.vue';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import PdvDetailsModal from '~/components/dashboard/PdvDetailsModal.vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import Dialog from 'primevue/dialog';
import { BadgeCheck, CalendarDays, Contact, DollarSign, Landmark, MapPin, Package, Pencil, Phone, Store, Tag as TagIcon, Trash2, UserRound, X } from '@lucide/vue';

definePageMeta({ layout: 'dashboard' });

useHead({ title: 'Pontos de Venda - SuperAdmin | Identifica Trânsito' });

const { $api } = useNuxtApp();
const confirm = useConfirm();
const toast = useToast();

interface PickupPoint {
    access?: { configured: boolean; email: string | null };
    commission_percentage?: string | number | null;
    id: number;
    name: string;
    cnpj: string | null;
    address: string | null;
    phone: string | null;
    responsible: string | null;
    map_link?: string | null;
    active: number | boolean;
    created_at: string;
    updated_at?: string | null;
    deleted_at?: string | null;
}

interface PdvPerformance {
    pdv_id: number | null;
    total_sales_qty: number;
    total_sales_amount: number;
    commission_amount: number;
}

const points = ref<PickupPoint[]>([]);
const loading = ref(false);
const search = ref('');
const sortBy = ref('recent');
const filtersOpen = ref(false);
const selectedPoint = ref<PickupPoint | null>(null);
const detailsOpen = ref(false);
const performance = ref<PdvPerformance[]>([]);
const loadingPerformance = ref(false);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0 });
const activePoints = computed(() => points.value.filter((point) => Boolean(point.active)).length);
const sortOptions = [
    { label: 'Mais recentes', value: 'recent' },
    { label: 'Mais etiquetas vendidas', value: 'tags-desc' },
    { label: 'Menos etiquetas vendidas', value: 'tags-asc' },
    { label: 'Maior faturamento', value: 'revenue-desc' },
    { label: 'Menor faturamento', value: 'revenue-asc' },
    { label: 'Maior repasse', value: 'commission-desc' },
    { label: 'Menor repasse', value: 'commission-asc' },
];

const fetchPoints = async (page = 1) => {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page), per_page: '15' });
        if (search.value) params.set('q', search.value);

        const res = await $api(`/admin/pickup-points?${params}`) as any;
        const payload = res?.data ?? res;
        const meta = res?.meta ?? payload?.meta ?? {};

        if (Array.isArray(payload)) {
            points.value = payload;
        } else if (Array.isArray(payload?.data)) {
            points.value = payload.data;
        } else {
            points.value = [];
        }

        pagination.value.currentPage = Number(meta?.current_page ?? payload?.current_page ?? page);
        pagination.value.lastPage = Number(meta?.last_page ?? payload?.last_page ?? 1);
        pagination.value.total = Number(meta?.total ?? payload?.total ?? points.value.length);
    } catch (e) {
        console.error('Erro ao carregar pontos de venda:', e);
    } finally {
        loading.value = false;
    }
};

const goToPage = (page: number) => {
    if (page < 1 || page > pagination.value.lastPage || page === pagination.value.currentPage || loading.value) return;
    fetchPoints(page);
};

const onSearch = () => {
    pagination.value.currentPage = 1;
    fetchPoints(1);
};
const scheduleSearch = () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(onSearch, 280);
};
const openPointDetails = (point: PickupPoint) => {
    selectedPoint.value = point;
    detailsOpen.value = true;
};

const fetchPerformance = async () => {
    loadingPerformance.value = true;
    try {
        const res = await $api('/admin/pdv-commissions?per_page=100') as any;
        performance.value = Array.isArray(res?.data) ? res.data : [];
    } catch (e) {
        console.error('Erro ao carregar indicadores dos pontos de venda:', e);
        performance.value = [];
    } finally {
        loadingPerformance.value = false;
    }
};

const pointPerformance = (pointId: number) => performance.value.find((item) => Number(item.pdv_id) === pointId);

const sortedPoints = computed(() => [...points.value].sort((first, second) => {
    const firstPerformance = pointPerformance(first.id);
    const secondPerformance = pointPerformance(second.id);
    const firstValue = sortBy.value.startsWith('tags') ? Number(firstPerformance?.total_sales_qty ?? 0)
        : sortBy.value.startsWith('revenue') ? Number(firstPerformance?.total_sales_amount ?? 0)
            : Number(firstPerformance?.commission_amount ?? 0);
    const secondValue = sortBy.value.startsWith('tags') ? Number(secondPerformance?.total_sales_qty ?? 0)
        : sortBy.value.startsWith('revenue') ? Number(secondPerformance?.total_sales_amount ?? 0)
            : Number(secondPerformance?.commission_amount ?? 0);
    if (sortBy.value.endsWith('desc')) return secondValue - firstValue;
    if (sortBy.value.endsWith('asc')) return firstValue - secondValue;
    return new Date(second.created_at).getTime() - new Date(first.created_at).getTime();
}));

const formatCurrency = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value ?? 0));

const formatDate = (d: string) => {
    if (!d) return '-';
    if (d.includes('/')) return d.split(' ')[0];
    return new Date(d).toLocaleDateString('pt-BR');
};

const formatCnpj = (cnpj: string | null) => {
    if (!cnpj) return '-';
    const c = cnpj.replace(/\D/g, '');
    return c.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
};

const handleDelete = (point: PickupPoint) => {
    confirm.require({
        message: `Tem certeza que deseja excluir o ponto de venda "${point.name}"?`,
        header: 'Confirmar Exclusão',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Não',
        acceptLabel: 'Sim, excluir',
        rejectClass: 'p-button-secondary',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await $api(`/admin/pickup-points/${point.id}`, { method: 'DELETE' });
                toast.add({ severity: 'success', summary: 'Sucesso!', detail: 'Ponto de venda excluído com sucesso.', life: 3000 });
                fetchPoints(pagination.value.currentPage);
            } catch (error: any) {
                const msg = error?.data?.message || error?.message || 'Não foi possível excluir o ponto de venda.';
                toast.add({ severity: 'error', summary: 'Erro ao excluir', detail: msg, life: 5000 });
            }
        },
    });
};

onMounted(() => {
    fetchPoints();
    fetchPerformance();
});
</script>

<template>
    <div class="admin-page min-h-[calc(100vh-7rem)] space-y-6 md:space-y-7">
        <HeroSection
            title="Pontos de Venda"
            subtitle="Organize os pontos de venda, retirada e atendimento da sua rede."
            greeting="Rede de atendimento"
            :showButton="true"
            buttonLabel="Novo Ponto de Venda"
            buttonLink="/dashboard/superadmin/pontos-de-venda/novo"
            buttonIcon="pi pi-plus"
        />

        <section class="admin-orders-page rounded-2xl border border-gray-100 bg-white p-5 shadow-sm md:p-6">
            <div class="mb-4 flex flex-col gap-4 border-b border-gray-100 pb-5 lg:flex-row lg:justify-between"><div><h2 class="text-[#172b4d]" style="margin:0 0 6px;font-size:25px;font-weight:600;line-height:1.1;">Gerenciamento de pontos de venda</h2><p class="text-[#52667f]" style="margin:0;font-size:15px;line-height:1.35;">Acompanhe os pontos de venda cadastrados na plataforma.</p></div><div class="flex items-center gap-4 self-start text-xs font-semibold uppercase tracking-wide text-[#52667f] lg:self-end"><span class="inline-flex items-center gap-1.5"><Package :size="15" class="text-[#1f46ee]" />{{ pagination.total }} PDVs</span><span class="inline-flex items-center gap-1.5"><BadgeCheck :size="15" class="text-[#16803c]" />{{ activePoints }} ativos</span></div></div>
            <button type="button" class="mb-4 flex w-full items-center justify-between border-b border-gray-100 pb-4 text-[15px] font-medium text-[#52667f] md:hidden" @click="filtersOpen = !filtersOpen"><span><i class="pi pi-filter mr-2 text-xs"></i>Filtros</span><i :class="filtersOpen ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="text-xs"></i></button>
            <div :class="[filtersOpen ? 'max-h-[14rem] mb-5 border-b border-gray-100 pb-5 opacity-100' : 'max-h-0 overflow-hidden opacity-0 pointer-events-none', 'grid gap-3 transition-[max-height,opacity,margin,padding] duration-300 md:mb-5 md:max-h-none md:grid-cols-2 md:overflow-visible md:border-b md:border-gray-100 md:pb-5 md:opacity-100 md:pointer-events-auto']">
                <div class="relative flex-1">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input
                        id="pdv-search"
                        v-model="search"
                        type="text"
                        placeholder="Buscar por nome, CNPJ ou cidade..."
                        class="admin-orders-search h-12 w-full rounded-lg border border-gray-300 pl-9 pr-4 text-sm focus:outline-none"
                        @input="scheduleSearch"
                    />
                </div>
                <SelectInput v-model="sortBy" :options="sortOptions" show-icon icon="pi pi-sort-alt" :icon-offset-y="2" wrapper-class="w-full" select-class="!h-12 !bg-[#fafafa]" list-class="admin-orders-sort-panel" />
            </div>

            <div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Skeleton v-for="n in 6" :key="n" height="13rem" borderRadius="1rem" />
            </div>

            <div v-else-if="points.length" class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="p in sortedPoints" :key="p.id" class="overflow-hidden rounded-2xl border border-[#e1e7f0] bg-white transition-shadow hover:shadow-md">
                    <button type="button" class="flex w-full items-start gap-3 p-5 text-left" @click="openPointDetails(p)">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#eef2ff] text-[#1f46ee]"><Store :size="19" /></span>
                        <span class="min-w-0 flex-1"><strong class="mb-1 block truncate text-[15px] text-[#172b4d]">{{ p.name }}</strong><span class="block truncate text-xs text-[#64748b]">{{ p.address || 'Endereço não informado' }}</span></span><i class="pi pi-external-link mt-1 text-sm text-[#8291a7]" title="Abrir detalhes"></i>
                    </button>
                    <NuxtLink :to="`/dashboard/superadmin/pontos-de-venda/${p.id}/editar`" class="mx-5 mb-3 block rounded-xl border border-[#e3e8f1] px-4 py-3 text-sm text-[#52667f]"><strong class="block text-[#172b4d]">Administrador do PDV</strong><span class="mt-1 block break-words">{{ p.access?.configured ? p.access.email : 'Configurar e-mail e senha de acesso' }}</span></NuxtLink>
                    <NuxtLink :to="`/dashboard/superadmin/pontos-de-venda/${p.id}/editar`" class="mx-5 mb-4 flex items-center justify-between rounded-xl bg-[#f3f5ff] px-4 py-3 text-sm text-[#172b4d]"><span>Repasse do PDV <strong class="ml-2">{{ formatPdvCommission(p.commission_percentage) }}</strong></span><span class="font-semibold text-it-primary">Editar <i class="pi pi-pencil ml-1" aria-hidden="true"></i></span></NuxtLink>
                    <dl class="grid grid-cols-3 border-t border-[#edf1f6] px-5 py-5 text-center"><div><dt class="text-[11px] font-bold uppercase tracking-wide text-[#8291a7]">Faturamento</dt><dd class="mt-1.5 text-sm font-bold text-[#172b4d]">{{ formatCurrency(pointPerformance(p.id)?.total_sales_amount) }}</dd></div><div class="border-x border-[#edf1f6]"><dt class="text-[11px] font-bold uppercase tracking-wide text-[#8291a7]">Etiquetas</dt><dd class="mt-1.5 text-sm font-bold text-[#172b4d]">{{ pointPerformance(p.id)?.total_sales_qty ?? 0 }}</dd></div><div><dt class="text-[11px] font-bold uppercase tracking-wide text-[#8291a7]">A repassar</dt><dd class="mt-1.5 text-sm font-bold text-[#172b4d]">{{ formatCurrency(pointPerformance(p.id)?.commission_amount) }}</dd></div></dl>
                    <div v-if="false" class="border-t border-[#edf1f6] bg-[#fbfcfe] px-5 pb-5 pt-4">
                        <div class="mb-4 rounded-xl border border-[#e3eaff] bg-[#f4f7ff] p-3">
                            <div class="mb-3 flex items-center justify-between gap-2"><span class="flex items-center gap-2 text-xs font-bold text-[#3156d9]"><i class="pi pi-chart-line"></i>Desempenho do PDV</span><span class="text-[10px] text-[#8291a7]">Acumulado no período</span></div>
                            <div v-if="loadingPerformance" class="grid grid-cols-3 gap-2"><Skeleton v-for="n in 3" :key="n" height="2.8rem" borderRadius=".5rem" /></div>
                            <div v-else class="grid gap-2 sm:grid-cols-3"><div class="rounded-lg border border-white bg-white px-3 py-2.5 shadow-sm"><p class="text-[10px] font-semibold uppercase tracking-wide text-[#8291a7]">Faturamento</p><p class="mt-1 text-base font-bold text-[#172b4d]">{{ formatCurrency(pointPerformance(p.id)?.total_sales_amount) }}</p></div><div class="rounded-lg border border-white bg-white px-3 py-2.5 shadow-sm"><p class="text-[10px] font-semibold uppercase tracking-wide text-[#8291a7]">Etiquetas vendidas</p><p class="mt-1 text-base font-bold text-[#172b4d]">{{ pointPerformance(p.id)?.total_sales_qty ?? 0 }}</p></div><div class="rounded-lg border border-white bg-white px-3 py-2.5 shadow-sm"><p class="text-[10px] font-semibold uppercase tracking-wide text-[#8291a7]">Valor a repassar</p><p class="mt-1 text-base font-bold text-[#172b4d]">{{ formatCurrency(pointPerformance(p.id)?.commission_amount) }}</p></div></div>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm"><div><dt class="text-[10px] font-bold uppercase text-[#8291a7]">CNPJ</dt><dd class="mt-1 font-mono text-xs text-[#52667f]">{{ formatCnpj(p.cnpj) }}</dd></div><div><dt class="text-[10px] font-bold uppercase text-[#8291a7]">Cadastro</dt><dd class="mt-1 text-[#52667f]">{{ formatDate(p.created_at) }}</dd></div><div><dt class="text-[10px] font-bold uppercase text-[#8291a7]">Telefone</dt><dd class="mt-1 text-[#52667f]">{{ p.phone || '-' }}</dd></div><div><dt class="text-[10px] font-bold uppercase text-[#8291a7]">Responsável</dt><dd class="mt-1 text-[#52667f]">{{ p.responsible || '-' }}</dd></div></dl>
                        <div class="mt-4 flex gap-2"><NuxtLink :to="`/dashboard/superadmin/pontos-de-venda/${p.id}/editar`" class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-[#d8dee8] py-2 text-xs font-semibold text-[#52667f] hover:bg-[#eef2ff] hover:text-[#1f46ee]"><i class="pi pi-pen-to-square"></i>Editar</NuxtLink><button @click="handleDelete(p)" class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-red-200 py-2 text-xs font-semibold text-red-500 hover:bg-red-50"><i class="pi pi-trash"></i>Excluir</button></div>
                    </div>
                </article>
            </div>

            <Dialog v-if="false" v-model:visible="detailsOpen" modal dismissable-mask :draggable="false" :show-header="false" class="w-[calc(100vw-2rem)] max-w-2xl overflow-hidden" @hide="selectedPoint = null">
                <div v-if="selectedPoint" class="relative space-y-5 p-2">
                    <button type="button" class="absolute right-2 top-2 flex h-9 w-9 items-center justify-center rounded-full text-[#64748b] transition hover:bg-[#f1f5f9] hover:text-[#1f46ee]" aria-label="Fechar detalhes" @click="detailsOpen = false"><X :size="19" /></button>
                    <header class="border-b border-[#edf1f6] pb-4 pr-10"><p class="mb-2 text-[10px] font-bold uppercase tracking-[0.14em] text-it-primary">Visão do ponto de venda</p><div class="flex items-center gap-3"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#eef1ff] text-it-primary"><MapPin :size="22" /></span><div class="min-w-0"><h3 class="m-0 truncate text-xl font-bold text-[#172b4d]">{{ selectedPoint.name }}</h3><p class="mt-1 text-sm text-[#64748b]">{{ selectedPoint.address || 'Endereço não informado' }}</p></div></div></header>
                    <section class="rounded-xl border border-[#dce5ff] bg-[#f5f8ff] p-3"><div class="mb-3 flex items-center justify-between gap-2"><h4 class="m-0 text-[15px] font-semibold text-[#172b4d]">Desempenho do PDV</h4><span class="text-xs text-[#64748b]">Acumulado no período</span></div><div v-if="loadingPerformance" class="grid gap-3 sm:grid-cols-3"><Skeleton v-for="n in 3" :key="n" height="4.5rem" borderRadius=".5rem" /></div><div v-else class="grid gap-3 sm:grid-cols-3"><div class="flex items-center gap-3 rounded-lg border border-[#e5eaf5] bg-white p-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#eef1ff] text-it-primary"><DollarSign :size="17" /></span><div><p class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Faturamento</p><p class="mt-1 text-lg font-bold text-[#101b38]">{{ formatCurrency(pointPerformance(selectedPoint.id)?.total_sales_amount) }}</p></div></div><div class="flex items-center gap-3 rounded-lg border border-[#e5eaf5] bg-white p-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#eef1ff] text-it-primary"><TagIcon :size="17" /></span><div><p class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Etiquetas vendidas</p><p class="mt-1 text-lg font-bold text-[#101b38]">{{ pointPerformance(selectedPoint.id)?.total_sales_qty ?? 0 }}</p></div></div><div class="flex items-center gap-3 rounded-lg border border-[#e5eaf5] bg-white p-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#eef1ff] text-it-primary"><Landmark :size="17" /></span><div><p class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Valor a repassar</p><p class="mt-1 text-lg font-bold text-[#101b38]">{{ formatCurrency(pointPerformance(selectedPoint.id)?.commission_amount) }}</p></div></div></div></section>
                    <dl class="grid overflow-hidden rounded-xl border border-[#e1e8f3] sm:grid-cols-2"><div class="flex items-center gap-3 border-b border-[#e9eef6] p-3 sm:border-r sm:border-r-[#e9eef6]"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#eef1ff] text-it-primary"><Contact :size="17" /></span><div><dt class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">CNPJ</dt><dd class="mt-0.5 font-mono text-sm font-semibold text-[#172b4d]">{{ formatCnpj(selectedPoint.cnpj) }}</dd></div></div><div class="flex items-center gap-3 border-b border-[#e9eef6] p-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#eef1ff] text-it-primary"><CalendarDays :size="17" /></span><div><dt class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Cadastro</dt><dd class="mt-0.5 text-sm font-semibold text-[#172b4d]">{{ formatDate(selectedPoint.created_at) }}</dd></div></div><div class="flex items-center gap-3 p-3 sm:border-r sm:border-r-[#e9eef6]"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#eef1ff] text-it-primary"><Phone :size="17" /></span><div><dt class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Telefone</dt><dd class="mt-0.5 text-sm font-semibold text-[#172b4d]">{{ selectedPoint.phone || '-' }}</dd></div></div><div class="flex items-center gap-3 p-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#eef1ff] text-it-primary"><UserRound :size="17" /></span><div><dt class="text-[10px] font-bold uppercase tracking-wide text-[#7284a5]">Responsável</dt><dd class="mt-0.5 text-sm font-semibold text-[#172b4d]">{{ selectedPoint.responsible || '-' }}</dd></div></div></dl>
                    <footer class="flex gap-3"><NuxtLink :to="`/dashboard/superadmin/pontos-de-venda/${selectedPoint.id}/editar`" class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-[#1f46ee] py-3 text-sm font-semibold text-white transition hover:bg-[#173ad1]"><Pencil :size="16" />Editar</NuxtLink><button class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-red-300 bg-white py-3 text-sm font-semibold text-red-500 transition hover:bg-red-50" @click="handleDelete(selectedPoint)"><Trash2 :size="16" />Excluir</button></footer>
                </div>
            </Dialog>
            <PdvDetailsModal v-model:visible="detailsOpen" :point="selectedPoint" :performance="selectedPoint ? pointPerformance(selectedPoint.id) : undefined" :loading="loadingPerformance" @delete="handleDelete" />

            <div v-if="!loading && !points.length" class="flex flex-col items-center justify-center py-12 gap-2 text-gray-400">
                <i class="pi pi-map-marker text-4xl"></i>
                <p>Nenhum ponto de venda encontrado</p>
            </div>

            <div class="mt-5">
                <PaginationControls
                    :current-page="pagination.currentPage"
                    :last-page="pagination.lastPage"
                    :loading="loading"
                    @page-change="goToPage"
                />
            </div>
        </section>
    </div>
</template>
