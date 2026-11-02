<script setup lang="ts">
import HeroSection from '~/components/dashboard/HeroSection.vue';
import PaginationControls from '~/components/PaginationControls.vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { BadgeCheck, Palette, Tag } from '@lucide/vue';

definePageMeta({ layout: 'dashboard' });
useHead({ title: 'Etiquetas - SuperAdmin | Identifica Trânsito' });

const { $api } = useNuxtApp();
const confirm = useConfirm();
const toast = useToast();
const config = useRuntimeConfig();
const assetWithBase = (path: string) => path.startsWith('data:') || path.startsWith('http') ? path : `${config.app.baseURL}${path}`.replace(/\/+/g, '/').replace(':/', '://');

interface TagItem { id: number; name: string; slug: string; description: string | null; color_name: string | null; price: string; created_at: string; }

const tags = ref<TagItem[]>([]);
const loading = ref(false);
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0 });

const fetchTags = async (page = 1) => {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page), per_page: '15' });
        const res = await $api(`/admin/tags?${params}`) as any;
        tags.value = Array.isArray(res?.data) ? res.data : [];
        pagination.value.currentPage = Number(res?.meta?.current_page ?? page);
        pagination.value.lastPage = Number(res?.meta?.last_page ?? 1);
        pagination.value.total = Number(res?.meta?.total ?? tags.value.length);
    } catch (error) {
        console.error('Erro ao carregar etiquetas:', error);
    } finally {
        loading.value = false;
    }
};

const goToPage = (page: number) => { if (page >= 1 && page <= pagination.value.lastPage && page !== pagination.value.currentPage && !loading.value) fetchTags(page); };
const formatDate = (date: string) => !date ? '-' : date.includes('/') ? date.split(' ')[0] : new Date(date).toLocaleDateString('pt-BR');
const formatCurrency = (value: string | number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
const colorLabel = (tag: TagItem) => tag.color_name ? tag.color_name.charAt(0).toUpperCase() + tag.color_name.slice(1) : 'Sem cor';

const handleDelete = (tag: TagItem) => {
    confirm.require({
        message: `Tem certeza que deseja excluir a etiqueta "${tag.name}"?`, header: 'Confirmar Exclusão', icon: 'pi pi-exclamation-triangle', rejectLabel: 'Não', acceptLabel: 'Sim, excluir', rejectClass: 'p-button-secondary', acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await $api(`/admin/tags/${tag.id}`, { method: 'DELETE' });
                toast.add({ severity: 'success', summary: 'Sucesso!', detail: 'Etiqueta excluída com sucesso.', life: 3000 });
                fetchTags(pagination.value.currentPage);
            } catch (error: any) {
                toast.add({ severity: 'error', summary: 'Erro ao excluir', detail: error?.data?.message || error?.message || 'Não foi possível excluir a etiqueta.', life: 5000 });
            }
        },
    });
};

onMounted(() => fetchTags());
</script>

<template>
    <div class="admin-page space-y-6 md:space-y-7">
        <HeroSection title="Etiquetas" subtitle="Organize o catálogo de etiquetas disponível para identificação veicular." greeting="Catálogo de identificação" :showButton="true" buttonLabel="Nova Etiqueta" buttonLink="/dashboard/superadmin/etiquetas/novo" buttonIcon="pi pi-plus" />

        <section class="tag-catalog bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <header class="mb-6 flex flex-col gap-4 border-b border-gray-100 pb-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-[#172b4d]" style="margin:0 0 6px;font-size:25px;font-weight:600;line-height:1.1;">Catálogo de etiquetas</h2>
                    <p class="mt-1.5 mb-0 text-[15px] leading-[1.35] text-[#52667f]">Visualize e mantenha os modelos disponíveis para compra.</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-semibold uppercase tracking-wide text-[#52667f]"><span class="inline-flex items-center gap-1.5"><Tag :size="15" class="text-[#1f46ee]" />{{ pagination.total }} modelos</span><span class="inline-flex items-center gap-1.5"><BadgeCheck :size="15" class="text-[#16803c]" />Catálogo ativo</span></div>
            </header>

            <div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="n in 6" :key="n" class="overflow-hidden rounded-2xl border border-[#e4eaf2]"><Skeleton height="11rem" /><div class="space-y-3 p-4"><Skeleton width="55%" height="1rem" /><Skeleton width="100%" height="0.8rem" /><Skeleton width="75%" height="0.8rem" /></div></div>
            </div>

            <div v-else-if="tags.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="tag in tags" :key="tag.id" class="tag-catalog-card overflow-hidden rounded-2xl border border-[#e1e8f3] bg-white">
                    <div class="relative flex h-48 items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_50%_35%,#ffffff_0%,#f4f6fd_47%,#e8eeff_100%)]">
                        <div class="absolute inset-x-0 bottom-0 h-14 bg-[linear-gradient(180deg,transparent,rgba(31,70,238,.08))]"></div>
                        <img v-if="tag.color_name" :src="assetWithBase(`/images/dashboard/etiquetas-v2/${tag.color_name}.svg`)" :alt="tag.name" class="relative z-10 h-32 w-32 object-contain drop-shadow-[0_12px_14px_rgba(31,70,238,.16)]" />
                        <span v-else class="relative z-10 flex h-20 w-20 items-center justify-center rounded-full bg-white text-[#1f46ee]"><Palette :size="34" /></span>
                        <span class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full border border-white/80 bg-white/90 px-2.5 py-1 text-xs font-semibold text-[#52667f] backdrop-blur"><Palette :size="13" />{{ colorLabel(tag) }}</span>
                    </div>
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="m-0 truncate text-base font-semibold text-[#172b4d]">{{ tag.name }}</h3><p class="mt-1 mb-0 text-[13px] text-[#64748b]">Cadastrada em {{ formatDate(tag.created_at) }}</p></div><span class="shrink-0 rounded-lg bg-[#eef2ff] px-2.5 py-1.5 text-sm font-semibold text-[#1f46ee]">{{ formatCurrency(tag.price) }}</span></div>
                        <p class="mt-4 mb-0 min-h-11 text-[15px] leading-6 text-[#52667f]">{{ tag.description || 'Sem descrição cadastrada para esta etiqueta.' }}</p>
                        <footer class="mt-4 flex gap-2 border-t border-[#edf1f6] pt-3">
                            <NuxtLink :to="`/dashboard/superadmin/etiquetas/${tag.id}/editar`" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg border border-[#d8dee8] px-4 py-3 text-sm font-semibold text-[#52667f] transition hover:border-[#b9c7ff] hover:bg-[#eef2ff] hover:text-[#1f46ee]"><i class="pi pi-pen-to-square"></i>Editar etiqueta</NuxtLink>
                            <button type="button" class="inline-flex !h-[52px] !w-[52px] shrink-0 items-center justify-center rounded-lg border border-red-200 text-red-500 transition hover:border-red-300 hover:bg-red-50" title="Excluir etiqueta" aria-label="Excluir etiqueta" @click="handleDelete(tag)"><i class="pi pi-trash text-sm"></i></button>
                        </footer>
                    </div>
                </article>
            </div>

            <div v-else class="flex flex-col items-center justify-center gap-2 py-14 text-slate-400"><i class="pi pi-tag text-4xl"></i><p class="m-0">Nenhuma etiqueta encontrada</p></div>

            <div class="mt-6"><PaginationControls :current-page="pagination.currentPage" :last-page="pagination.lastPage" :loading="loading" @page-change="goToPage" /></div>
        </section>
    </div>
</template>

<style scoped>
@media (max-width: 639px) {
    .tag-catalog { padding: 1.5rem !important; }
    .tag-catalog h2 { font-size: 22px !important; margin-top: 12px !important; margin-bottom: 0 !important; }
    .tag-catalog-card h3 { font-size: 22px !important; font-weight: 600 !important; }
}
</style>
