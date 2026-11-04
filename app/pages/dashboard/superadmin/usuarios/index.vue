<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm';
import AdminUsersPage from '~/pages/dashboard/admin/usuarios/index.vue';
import { useToast } from 'primevue/usetoast';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import StatsCard from '~/components/dashboard/StatsCard.vue';
import PaginationControls from '~/components/PaginationControls.vue';
import { UserMinus, UsersRound } from '@lucide/vue';

definePageMeta({ layout: 'dashboard' });

useHead({ title: 'Usuários - SuperAdmin | Identifica Trânsito' });

const { $api } = useNuxtApp();
const confirm = useConfirm();
const toast = useToast();

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
        const res = await $api('/admin/users/totals') as any;
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
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0 });

const fetchUsers = async (page = 1) => {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page), per_page: '15' });
        if (search.value) params.set('q', search.value);
        const res = await $api(`/admin/users?${params}`) as any;
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
                await $api(`/admin/users/${user.id}`, { method: 'DELETE' });
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

// A listagem visível reutiliza a implementação validada do Admin.
</script>

<template>
    <AdminUsersPage />
    <div v-if="false" class="admin-page space-y-6 md:space-y-7">
        <HeroSection
            title="Usuários"
            subtitle="Administre perfis, permissões e acessos de toda a plataforma."
            greeting="Gestão de acessos"
            :showButton="true"
            buttonLabel="Novo Usuário"
            buttonLink="/dashboard/superadmin/usuarios/novo"
            buttonIcon="pi pi-plus"
        />

        <p class="rounded-xl border border-[#e1e7f0] bg-white p-4 text-[#52667f]">Aqui você gerencia os clientes. Os administradores dos PDVs ficam em <NuxtLink to="/dashboard/superadmin/pontos-de-venda" class="font-semibold text-it-primary underline">Pontos de Venda</NuxtLink>.</p>
        <!-- Stats Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <StatsCard
                title="Usuários Ativos"
                :value="userStats.active"
                :icon="UsersRound"
                color="blue"
                :loading="loadingStats"
            />
            <StatsCard
                title="Usuários Excluídos"
                :value="userStats.deleted"
                :icon="UserMinus"
                color="orange"
                :loading="loadingStats"
            />
        </section>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <!-- Search -->
            <div class="flex flex-wrap items-center gap-3 mb-6">
                <div class="relative flex-1 min-w-[240px]">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por nome ou e-mail..."
                        class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none"
                        @keyup.enter="onSearch"
                    />
                </div>
                <button
                    @click="onSearch"
                    class="px-4 py-2 bg-it-primary text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition"
                >
                    Buscar
                </button>
                <span class="text-sm text-gray-500 ml-auto">{{ pagination.total }} usuário(s)</span>
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
            <div v-else-if="users.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-gray-700 font-semibold">#</th>
                            <th class="text-left py-3 px-4 text-gray-700 font-semibold">ID</th>
                            <th class="text-left py-3 px-4 text-gray-700 font-semibold">Foto</th>
                            <th class="text-left py-3 px-4 text-gray-700 font-semibold">Nome</th>
                            <th class="text-left py-3 px-4 text-gray-700 font-semibold">E-mail</th>
                            <th class="text-left py-3 px-4 text-gray-700 font-semibold">Tipo</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Veículos</th>
                            <th class="text-center py-3 px-4 text-gray-700 font-semibold">Etiquetas</th>
                            <th class="text-left py-3 px-4 text-gray-700 font-semibold">Cadastro</th>
                            <th class="text-right py-3 px-4 text-gray-700 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(u, index) in users"
                            :key="u.id"
                            class="border-b border-gray-100 hover:bg-gray-50 transition"
                        >   
                            <td class="py-3 px-4 text-gray-400 text-xs">{{ (index + 1) + (pagination.currentPage - 1) * 15 }}</td>
                            <td class="py-3 px-4 text-gray-400 text-xs">{{ u.id }}</td>
                            <td class="py-3 px-4">
                                <img
                                    :src="assetWithBase(u.photo || '/images/dashboard/avatar.jpg')"
                                    :alt="u.name"
                                    class="w-8 h-8 rounded-full object-cover"
                                />
                            </td>
                            <td class="py-3 px-4 font-medium text-gray-900">{{ u.name }}</td>
                            <td class="py-3 px-4 text-gray-600">{{ u.email }}</td>
                            <td class="py-3 px-4">
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
                            <td class="py-3 px-4 text-gray-500">{{ formatDate(u.created_at) }}</td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-2">
                                    <NuxtLink
                                        :to="`/dashboard/superadmin/usuarios/${u.id}/editar`"
                                        class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition"
                                        title="Editar"
                                    >
                                        <i class="pi pi-pencil text-sm"></i>
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

            <div v-else class="flex flex-col items-center justify-center py-12 gap-2 text-gray-400">
                <i class="pi pi-users text-4xl"></i>
                <p>Nenhum usuário encontrado</p>
            </div>

            <!-- Pagination -->
            <div class="mt-5 pt-4 border-t border-gray-100">
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
