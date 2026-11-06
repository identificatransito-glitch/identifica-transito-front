<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import PaginationControls from '~/components/PaginationControls.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import { BadgeCheck, Package } from '@lucide/vue';

definePageMeta({ layout: 'dashboard' });
const route = useRoute();
const isSuperAdminView = computed(() => route.path.startsWith('/dashboard/superadmin'));
const usersBaseRoute = computed(() => isSuperAdminView.value ? '/dashboard/superadmin/usuarios' : '/dashboard/admin/usuarios');
useHead({ title: computed(() => isSuperAdminView.value ? 'Usuários - SuperAdmin | Identifica Trânsito' : 'Usuários - Admin | Identifica Trânsito') });

const { $api } = useNuxtApp();
const confirm = useConfirm();
const toast = useToast();
const usersApiBase = computed(() => isSuperAdminView.value ? '/admin/users' : '/admin-pdv/users');

const config = useRuntimeConfig();
const assetWithBase = (path: string) => {
    if (path.startsWith('data:') || path.startsWith('http')) return path;
    return `${config.app.baseURL}${path}`.replace(/\/+/g, '/').replace(':/', '://');
};

interface User {
    id: number;
    name: string;
    email: string;
    phone?: string;
    type: string | number;
    active?: number | boolean;
    photo?: string | null;
    created_at: string;
    vehicles_count?: number;
    tag_purchases_count?: number;
}

// Stats
const loadingStats = ref(false);
const userStats = ref({ active: 0, deleted: 0 });

const fetchUserStats = async () => {
    loadingStats.value = true;
    try {
        const res = await $api(`${usersApiBase.value}/totals`) as any;
        const data = res?.data ?? res;
        userStats.value.active = data?.active ?? 0;
        userStats.value.deleted = data?.deleted ?? 0;
    } catch (e) {
        console.error('Erro ao carregar stats de usuários:', e);
    } finally {
        loadingStats.value = false;
    }
};

// Table
const users = ref<User[]>([]);
const loading = ref(false);
const search = ref('');
const sortBy = ref('recent');
const filtersOpen = ref(false);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0 });
const userSortOptions = [
    { label: 'Mais recentes', value: 'recent' }, { label: 'Mais antigos', value: 'oldest' },
    { label: 'Nome: A–Z', value: 'name-asc' }, { label: 'Nome: Z–A', value: 'name-desc' },
    { label: 'Mais veículos', value: 'vehicles-desc' }, { label: 'Mais etiquetas', value: 'tags-desc' }
];
const filteredUsers = computed(() => [...users.value].sort((first, second) => {
    if (sortBy.value === 'name-asc') return first.name.localeCompare(second.name);
    if (sortBy.value === 'name-desc') return second.name.localeCompare(first.name);
    if (sortBy.value === 'oldest') return new Date(first.created_at).getTime() - new Date(second.created_at).getTime();
    if (sortBy.value === 'vehicles-desc') return (second.vehicles_count ?? 0) - (first.vehicles_count ?? 0);
    if (sortBy.value === 'tags-desc') return (second.tag_purchases_count ?? 0) - (first.tag_purchases_count ?? 0);
    return new Date(second.created_at).getTime() - new Date(first.created_at).getTime();
}));

const fetchUsers = async (page = 1) => {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page), per_page: '15' });
        if (search.value) params.set('q', search.value);
        const res = await $api(`${usersApiBase.value}?${params}`) as any;
        users.value = Array.isArray(res?.data) ? res.data : [];
        pagination.value.currentPage = Number(res?.meta?.current_page ?? page);
        pagination.value.lastPage = Number(res?.meta?.last_page ?? 1);
        pagination.value.total = Number(res?.meta?.total ?? users.value.length);
    } catch (e) {
        console.error('Erro ao carregar usuários:', e);
    } finally {
        loading.value = false;
    }
};

const goToPage = (page: number) => {
    if (page < 1 || page > pagination.value.lastPage || page === pagination.value.currentPage || loading.value) return;
    fetchUsers(page);
};

const onSearch = () => {
    pagination.value.currentPage = 1;
    fetchUsers(1);
};
const scheduleSearch = () => { if (searchDebounce) clearTimeout(searchDebounce); searchDebounce = setTimeout(onSearch, 280); };

const typeLabel = (type: string | number) => {
    if (type === 'superAdmin' || type === 1) return { label: 'SuperAdmin', class: 'bg-purple-50 text-purple-700' };
    if (type === 'admin' || type === 2) return { label: 'Admin', class: 'bg-blue-50 text-blue-700' };
    return { label: 'Cliente', class: 'bg-gray-100 text-gray-700' };
};

const formatDate = (d: string) => {
    if (!d) return '-';
    if (d.includes('/')) return d.split(' ')[0];
    return new Date(d).toLocaleDateString('pt-BR');
};

const handleDelete = (user: User) => {
    if (!isSuperAdminView.value) return;
    confirm.require({
        message: `Tem certeza que deseja excluir o usuário "${user.name}"?`,
        header: 'Confirmar Exclusão',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Não',
        acceptLabel: 'Sim, excluir',
        rejectClass: 'p-button-secondary',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await $api(`${usersApiBase.value}/${user.id}`, { method: 'DELETE' });
                toast.add({ severity: 'success', summary: 'Sucesso!', detail: 'Usuário excluído com sucesso.', life: 3000 });
                fetchUserStats();
                fetchUsers(pagination.value.currentPage);
            } catch (error: any) {
                const msg = error?.data?.message || error?.message || 'Não foi possível excluir o usuário.';
                toast.add({ severity: 'error', summary: 'Erro ao excluir', detail: msg, life: 5000 });
            }
        }
    });
};

onMounted(() => {
    fetchUserStats();
    fetchUsers();
});
</script>

<template>
    <div class="admin-page space-y-6 md:space-y-7">
        <HeroSection
            title="Usuários"
            :subtitle="isSuperAdminView ? 'Gerencie todos os níveis de acesso cadastrados na plataforma.' : 'Consulte os clientes vinculados aos pedidos do seu ponto de venda. Cadastro e alterações são feitos pelo cliente ou pelo Super Admin.'"
            :greeting="isSuperAdminView ? 'Acessos da plataforma' : 'Acessos do seu PDV'"
            :showButton="isSuperAdminView"
            buttonLabel="Novo Usuário"
            :buttonLink="`${usersBaseRoute}/novo`"
            buttonIcon="pi pi-plus"
        />

        <div class="admin-orders-page bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="mb-4 flex flex-col gap-4 border-b border-gray-100 pb-5 lg:flex-row lg:justify-between"><div><h2 class="text-[#172b4d]" style="margin:0 0 6px;font-size:25px;font-weight:600;line-height:1.1;">Gerenciamento de usuários</h2><p class="text-[#52667f]" style="margin:0;font-size:15px;line-height:1.35;">Acompanhe os usuários cadastrados na plataforma.</p></div><div class="flex items-center gap-4 self-start text-xs font-semibold uppercase tracking-wide text-[#52667f] lg:self-end"><span class="inline-flex items-center gap-1.5"><Package :size="15" class="text-[#1f46ee]" />{{ pagination.total }} usuários</span><span class="inline-flex items-center gap-1.5"><BadgeCheck :size="15" class="text-[#16803c]" />{{ userStats.active }} ativos</span></div></div>
            <button type="button" class="mb-4 flex w-full items-center justify-between border-b border-gray-100 pb-4 text-[15px] font-medium text-[#52667f] md:hidden" @click="filtersOpen = !filtersOpen"><span><i class="pi pi-filter mr-2 text-xs"></i>Filtros</span><i :class="filtersOpen ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="text-xs"></i></button>
            <div :class="[filtersOpen ? 'max-h-[14rem] mb-6 border-b border-gray-100 pb-5 opacity-100' : 'max-h-0 overflow-hidden opacity-0 pointer-events-none', 'admin-orders-filters grid gap-3 transition-[max-height,opacity,margin,padding] duration-300 md:max-h-none md:overflow-visible md:pointer-events-auto md:mb-6 md:border-b md:border-gray-100 md:pb-5 md:opacity-100 md:grid-cols-2 xl:grid-cols-[minmax(25rem,1.45fr)_minmax(18rem,1fr)] xl:items-center']">
                <div class="relative">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por nome ou e-mail..."
                        class="admin-orders-search h-12 w-full pl-9 pr-4 border border-gray-300 rounded-lg text-sm focus:outline-none"
                        @input="scheduleSearch"
                    />
                </div>
                <SelectInput v-model="sortBy" :options="userSortOptions" show-icon icon="pi pi-sort-alt" :icon-offset-y="2" wrapper-class="admin-orders-filter-select" select-class="!h-12 !bg-[#fafafa]" list-class="admin-orders-sort-panel" />
            </div>

            <!-- Skeleton -->
            <div v-if="loading" class="space-y-3">
                <div v-for="n in 8" :key="n" class="flex gap-4 items-center p-3">
                    <Skeleton width="5%" height="14px" />
                    <Skeleton shape="circle" width="32px" height="32px" />
                    <Skeleton width="20%" height="14px" />
                    <Skeleton width="28%" height="14px" />
                    <Skeleton width="10%" height="20px" />
                    <Skeleton width="10%" height="14px" />
                    <Skeleton width="8%" height="28px" />
                </div>
            </div>

            <!-- Table -->
            <template v-else-if="filteredUsers.length > 0">
            <div class="admin-orders-table hidden overflow-x-auto rounded-xl border border-[#e8edf5] md:block">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-gray-700 font-semibold">Nome</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">E-mail</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Tipo</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Veículos</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Tags</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Cadastro</th>
                            <th v-if="isSuperAdminView" class="text-center py-3 px-4 text-gray-700 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="u in filteredUsers"
                            :key="u.id"
                            class="border-b border-gray-100 hover:bg-gray-50 transition"
                        >   
                            <td class="py-3 px-4"><div class="flex items-center gap-2.5">
                                <img
                                    :src="assetWithBase(u.photo || '/images/dashboard/avatar.jpg')"
                                    :alt="u.name"
                                    class="w-8 h-8 rounded-full object-cover"
                                /><span class="font-medium text-gray-900">{{ u.name }}</span></div>
                            </td>
                            <td class="py-3 px-4 text-center text-gray-600">{{ u.email || '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="typeLabel(u.type).class">
                                    {{ typeLabel(u.type).label }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center gap-1 text-gray-700 font-medium">
                                    <i class="pi pi-car text-gray-400 text-xs"></i>
                                    {{ u.vehicles_count ?? 0 }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center gap-1 text-gray-700 font-medium">
                                    <i class="pi pi-tag text-gray-400 text-xs"></i>
                                    {{ u.tag_purchases_count ?? 0 }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center text-gray-500">{{ formatDate(u.created_at) }}</td>
                            <td v-if="isSuperAdminView" class="py-3 px-4">
                                <div class="flex items-center justify-center gap-2">
                                    <NuxtLink
                                        :to="`${usersBaseRoute}/${u.id}/editar`"
                                        class="p-1.5 rounded-lg text-[#64748b] hover:bg-[#eef2ff] hover:text-[#1f46ee] transition"
                                        title="Editar"
                                    >
                                        <i class="pi pi-pen-to-square text-sm"></i>
                                    </NuxtLink>
                                    <button
                                        @click="handleDelete(u)"
                                        class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition"
                                        title="Excluir"
                                    >
                                        <i class="pi pi-trash text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!loading && filteredUsers.length > 0" class="grid gap-3 md:hidden">
                <article v-for="u in filteredUsers" :key="u.id" class="rounded-2xl border border-[#e4eaf2] bg-white p-4">
                    <header class="flex items-center gap-3">
                        <img :src="assetWithBase(u.photo || '/images/dashboard/avatar.jpg')" :alt="u.name" class="h-11 w-11 rounded-full object-cover ring-2 ring-white" />
                        <div class="min-w-0 flex-1"><p class="mb-0 truncate text-sm font-bold text-[#172b4d]">{{ u.name }}</p><p class="mt-0.5 mb-0 truncate text-xs text-[#52667f]">{{ u.email || '—' }}</p></div>
                        <span :class="typeLabel(u.type).class" class="shrink-0 rounded-full px-2 py-1 text-xs font-medium">{{ typeLabel(u.type).label }}</span>
                    </header>
                    <dl class="mt-4 grid grid-cols-3 border-t border-[#edf1f6] pt-3 text-center text-xs text-[#64748b]">
                        <div><dt class="mb-1 text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Veículos</dt><dd class="m-0 font-semibold text-[#172b4d]">{{ u.vehicles_count ?? 0 }}</dd></div>
                        <div><dt class="mb-1 text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Tags</dt><dd class="m-0 font-semibold text-[#172b4d]">{{ u.tag_purchases_count ?? 0 }}</dd></div>
                        <div><dt class="mb-1 text-[10px] font-bold uppercase tracking-wide text-[#8291a7]">Cadastro</dt><dd class="m-0 font-semibold text-[#172b4d]">{{ formatDate(u.created_at) }}</dd></div>
                    </dl>
                    <div v-if="isSuperAdminView" class="mt-4 flex gap-2 border-t border-[#edf1f6] pt-3">
                        <NuxtLink :to="`${usersBaseRoute}/${u.id}/editar`" class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-[#d8dee8] py-2 text-xs font-semibold text-[#52667f] hover:bg-[#eef2ff] hover:text-[#1f46ee]"><i class="pi pi-pen-to-square"></i>Editar</NuxtLink>
                        <button @click="handleDelete(u)" class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-red-200 py-2 text-xs font-semibold text-red-500 hover:bg-red-50"><i class="pi pi-trash"></i>Excluir</button>
                    </div>
                </article>
            </div>
            </template>

            <div v-else class="flex flex-col items-center justify-center py-12 gap-2 text-gray-400">
                <i class="pi pi-users text-4xl"></i>
                <p>Nenhum usuário encontrado</p>
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
    </div>
</template>
