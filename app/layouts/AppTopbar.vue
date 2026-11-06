<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useLayout } from '@/layouts/composables/layout';
import { useToast } from 'primevue/usetoast';
import { CalendarDays, CarFront, CircleHelp, CircleUserRound, CreditCard, House, LayoutDashboard, List, LockKeyhole, LogOut, MapPin, Menu, Phone, Search, Settings, Tag, Ticket, UsersRound, X } from '@lucide/vue';

const { toggleMenu } = useLayout();
const route = useRoute();
const showProfileMenu = ref(false);
const showNotifications = ref(false);
const clientSearchTerm = ref('');
const showSearchSuggestions = ref(false);
const clientSearchWrapper = ref(null);
const appNotifications = ref([]);
const dismissingNotificationIds = ref([]);
const delayEmptyNotifications = ref(false);
const auth = useAuth();
auth.init();

const toast = useToast();
const config = useRuntimeConfig();
const showLogoutModal = ref(false);
const isLoggingOut = ref(false);
const userName = computed(() => auth.user.value?.name || 'Usuário');
const userEmail = computed(() => auth.user.value?.email || '');
const lastAvatar = ref('');
const defaultAvatar = `${config.app.baseURL}images/dashboard/avatar.jpg`.replace(/\/+/g, '/');
const userAvatar = computed(() => auth.user.value?.photo || lastAvatar.value || defaultAvatar);
const isClient = computed(() => {
    const type = auth.user.value?.type;
    return type === 'client' || type === 'cliente' || type === 3;
});
const isAdmin = computed(() => {
    const type = auth.user.value?.type;
    return type === 'admin' || type === 2 || route.path.startsWith('/dashboard/admin');
});
const isSuperAdmin = computed(() => {
    const type = auth.user.value?.type;
    return type === 'superAdmin' || type === 1 || route.path.startsWith('/dashboard/superadmin');
});
const usesProductLayout = computed(() => isClient.value || isAdmin.value || isSuperAdmin.value);
const dashboardHome = computed(() => isSuperAdmin.value ? '/dashboard/superadmin' : isAdmin.value ? '/dashboard/admin' : '/dashboard');
const managementOrdersRoute = computed(() => isSuperAdmin.value ? '/dashboard/superadmin/pedidos' : '/dashboard/admin/pedidos');
const pageTitle = computed(() => {
    if (route.path.startsWith('/dashboard/superadmin/usuarios/') && route.path.endsWith('/editar')) return 'Editar Usuário';
    if (route.path.startsWith('/dashboard/superadmin/usuarios/novo')) return 'Novo Usuário';
    if (route.path.startsWith('/dashboard/superadmin/usuarios')) return 'Usuários';
    if (route.path.startsWith('/dashboard/superadmin/veiculos')) return 'Veículos';
    if (route.path.startsWith('/dashboard/superadmin/etiquetas')) return 'Etiquetas';
    if (route.path.startsWith('/dashboard/superadmin/pedidos')) return 'Pedidos';
    if (route.path.startsWith('/dashboard/superadmin/cupons-de-desconto')) return 'Cupons de Desconto';
    if (route.path.startsWith('/dashboard/superadmin/frete')) return 'Frete';
    if (route.path.startsWith('/dashboard/superadmin/pontos-de-venda')) return 'Pontos de Venda';
    if (route.path.startsWith('/dashboard/superadmin/faq')) return 'FAQ';
    if (route.path === '/dashboard/superadmin') return 'Início';
    if (route.path.startsWith('/dashboard/admin/usuarios/') && route.path.endsWith('/editar')) return 'Editar Usuário';
    if (route.path.startsWith('/dashboard/admin/usuarios/novo')) return 'Novo Usuário';
    if (route.path.startsWith('/dashboard/admin/usuarios')) return 'Usuários';
    if (route.path.startsWith('/dashboard/admin/veiculos')) return 'Veículos';
    if (route.path.startsWith('/dashboard/admin/pedidos')) return 'Pedidos';
    if (route.path === '/dashboard/admin') return 'Início';
    if (route.path.startsWith('/dashboard/veiculos/editar/')) return 'Editar Veículo';
    if (route.path.startsWith('/dashboard/veiculos/detalhes/')) return 'Detalhes do Veículo';
    if (route.path.startsWith('/dashboard/veiculos/novo')) return 'Novo Veículo';
    if (route.path.startsWith('/dashboard/veiculos')) return 'Meus Veículos';
    if (route.path.startsWith('/dashboard/pagamento')) return 'Finalizar compra';
    return ({
    '/dashboard': 'Início',
    '/dashboard/veiculos': 'Meus Veículos',
    '/dashboard/etiquetas': 'Minhas Etiquetas',
    '/dashboard/pedidos': 'Meus Pedidos',
    '/dashboard/suporte': 'Suporte',
    '/dashboard/configuracoes': 'Configurações',
    '/dashboard/perfil': 'Meu Perfil',
    '/dashboard/notificacoes-motoristas': 'Notificar um Motorista!'
    })[route.path] || 'Área do cliente';
});
const pageIcon = computed(() => {
    if (route.path.startsWith('/dashboard/superadmin/usuarios')) return UsersRound;
    if (route.path.startsWith('/dashboard/superadmin/veiculos')) return CarFront;
    if (route.path.startsWith('/dashboard/superadmin/etiquetas')) return Tag;
    if (route.path.startsWith('/dashboard/superadmin/pedidos')) return List;
    if (route.path.startsWith('/dashboard/superadmin/cupons-de-desconto')) return Ticket;
    if (route.path.startsWith('/dashboard/superadmin/pontos-de-venda')) return MapPin;
    if (route.path.startsWith('/dashboard/superadmin/faq')) return CircleHelp;
    if (route.path.startsWith('/dashboard/admin/usuarios')) return UsersRound;
    if (route.path.startsWith('/dashboard/admin/veiculos')) return CarFront;
    if (route.path.startsWith('/dashboard/admin/pedidos')) return List;
    if (route.path.startsWith('/dashboard/veiculos')) return CarFront;
    if (route.path.startsWith('/dashboard/etiquetas')) return Tag;
    if (route.path.startsWith('/dashboard/pedidos')) return List;
    if (route.path.startsWith('/dashboard/suporte')) return CircleHelp;
    if (route.path.startsWith('/dashboard/configuracoes')) return Settings;
    if (route.path.startsWith('/dashboard/perfil')) return CircleUserRound;
    if (route.path.startsWith('/dashboard/notificacoes-motoristas')) return Phone;
    if (route.path.startsWith('/dashboard/pagamento')) return CreditCard;
    return LayoutDashboard;
});

watch(() => auth.user.value?.photo, (photo) => {
    if (photo) lastAvatar.value = photo;
}, { immediate: true });

const closeProfileMenu = () => { showProfileMenu.value = false; };
const openLogoutModal = () => {
    closeProfileMenu();
    showLogoutModal.value = true;
};
const closeLogoutModal = () => {
    if (!isLoggingOut.value) showLogoutModal.value = false;
};
const confirmLogout = async () => {
    if (isLoggingOut.value) return;
    isLoggingOut.value = true;
    const result = await auth.logout();
    if (!result?.ok) {
        const apiMessage = result?.error?.data?.message || result?.error?.data?.error;
        toast.add({ severity: 'error', summary: 'Erro ao sair', detail: apiMessage || 'Não foi possível encerrar sua sessão. Tente novamente.', life: 5000 });
        isLoggingOut.value = false;
        return;
    }
    isLoggingOut.value = false;
    showLogoutModal.value = false;
};
const clientSearchSuggestions = computed(() => {
    const clientPages = [
        { title: 'Início', description: 'Visão geral do painel', to: '/dashboard', icon: House },
        { title: 'Meus Veículos', description: 'Gerencie seus veículos', to: '/dashboard/veiculos', icon: CarFront },
        { title: 'Minhas Etiquetas', description: 'Encontre etiquetas para seus veículos', to: '/dashboard/etiquetas', icon: Tag },
        { title: 'Meus Pedidos', description: 'Acompanhe compras e entregas', to: '/dashboard/pedidos', icon: List },
        { title: 'Suporte', description: 'Encontre ajuda quando precisar', to: '/dashboard/suporte', icon: CircleHelp },
        { title: 'Configurações', description: 'Preferências da sua conta', to: '/dashboard/configuracoes', icon: Settings }
    ];
    const adminPages = [
        { title: 'Início', description: 'Visão geral administrativa', to: '/dashboard/admin', icon: House },
        { title: 'Usuários', description: 'Consulte os clientes do PDV', to: '/dashboard/admin/usuarios', icon: UsersRound },
        { title: 'Pedidos', description: 'Acompanhe pedidos e entregas', to: '/dashboard/admin/pedidos', icon: List }
    ];
    const superAdminPages = [
        { title: 'Frete', description: 'Configure a cobrança de entrega', to: '/dashboard/superadmin/frete', icon: MapPin },
        { title: 'Início', description: 'Visão geral da plataforma', to: '/dashboard/superadmin', icon: House },
        { title: 'Usuários', description: 'Gerencie todos os níveis de acesso', to: '/dashboard/superadmin/usuarios', icon: UsersRound },
        { title: 'Veículos', description: 'Consulte os veículos cadastrados', to: '/dashboard/superadmin/veiculos', icon: CarFront },
        { title: 'Etiquetas', description: 'Gerencie o catálogo de etiquetas', to: '/dashboard/superadmin/etiquetas', icon: Tag },
        { title: 'Pedidos', description: 'Acompanhe pagamentos e entregas', to: '/dashboard/superadmin/pedidos', icon: List },
        { title: 'Cupons', description: 'Gerencie campanhas e descontos', to: '/dashboard/superadmin/cupons-de-desconto', icon: Ticket },
        { title: 'Pontos de Venda', description: 'Gerencie PDVs e comissões', to: '/dashboard/superadmin/pontos-de-venda', icon: MapPin },
        { title: 'FAQ', description: 'Gerencie dúvidas frequentes', to: '/dashboard/superadmin/faq', icon: CircleHelp }
    ];
    const pages = isSuperAdmin.value ? superAdminPages : isAdmin.value ? adminPages : clientPages;
    const term = clientSearchTerm.value.trim().toLocaleLowerCase('pt-BR');
    return term ? pages.filter((page) => `${page.title} ${page.description}`.toLocaleLowerCase('pt-BR').includes(term)) : pages;
});
const closeClientSearch = () => {
    showSearchSuggestions.value = false;
};
const unreadAppNotifications = computed(() => appNotifications.value.filter((notification) => !notification.read).length);

const fetchAppNotifications = async () => {
    if (!isClient.value) return;

    try {
        const { $api } = useNuxtApp();
        const response = await $api('/notifications');
        const payload = response?.data ?? response;
        appNotifications.value = Array.isArray(payload) ? payload : [];
    } catch {
        appNotifications.value = [];
    }
};

const handleNotificationClick = () => {
    showNotifications.value = !showNotifications.value;
};

watch(showNotifications, (isVisible) => {
    if (typeof document === 'undefined') return;

    document.documentElement.style.overflow = isVisible ? 'hidden' : '';
    document.documentElement.style.overflowX = isVisible ? 'clip' : '';
    document.body.style.overflow = isVisible ? 'hidden' : '';
    document.body.style.overflowX = isVisible ? 'clip' : '';
    document.documentElement.classList.toggle('notifications-open', isVisible);
    document.body.classList.toggle('notifications-open', isVisible);
});

const dismissNotification = (notificationId) => {
    if (dismissingNotificationIds.value.includes(notificationId)) return;

    delayEmptyNotifications.value = true;
    dismissingNotificationIds.value.push(notificationId);
    window.setTimeout(() => {
        appNotifications.value = appNotifications.value.filter((notification) => notification.id !== notificationId);
        dismissingNotificationIds.value = dismissingNotificationIds.value.filter((id) => id !== notificationId);

        window.setTimeout(() => {
            if (!appNotifications.value.length && !dismissingNotificationIds.value.length) {
                delayEmptyNotifications.value = false;
            }
        }, 750);
    }, 320);
};
onMounted(() => {
    fetchAppNotifications();
    document.addEventListener('click', handleOutsideSearchClick);
});

const handleOutsideSearchClick = (event) => {
    if (!clientSearchWrapper.value?.contains(event.target)) closeClientSearch();
};

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideSearchClick);
    document.documentElement.style.overflow = '';
    document.documentElement.style.overflowX = '';
    document.body.style.overflow = '';
    document.body.style.overflowX = '';
    document.documentElement.classList.remove('notifications-open');
    document.body.classList.remove('notifications-open');
});
</script>

<template>
    <header class="layout-topbar" :class="{ 'client-topbar': usesProductLayout }">
        <div class="layout-topbar-inner">
        <div class="layout-topbar-logo-container">
            <NuxtLink v-if="usesProductLayout" :to="dashboardHome" class="client-mobile-logo" aria-label="Identifica Trânsito - início">
                <img :src="`${config.app.baseURL}images/logo-horizontal.svg`.replace(/\/+/g, '/')" alt="Identifica Trânsito" />
            </NuxtLink>
            <button v-if="!usesProductLayout" class="layout-menu-button layout-topbar-action" aria-label="Alternar menu" @click="toggleMenu">
                <Menu :size="21" />
            </button>
            <router-link v-if="!usesProductLayout" to="/" class="layout-topbar-logo">
                <img :src="`${config.app.baseURL}images/logo-it-icon.svg`.replace(/\/+/g, '/')" alt="Logo" class="h-10" />
                <span>Identifica Trânsito</span>
            </router-link>
            <div v-else class="client-page-title">
                <component :is="pageIcon" :size="21" :stroke-width="1.9" />
                <span>{{ pageTitle }}</span>
            </div>
        </div>

        <div class="layout-topbar-actions">
            <div v-if="usesProductLayout" ref="clientSearchWrapper" class="client-search-wrapper">
                <label class="client-search" aria-label="Buscar no painel">
                    <Search :size="19" :stroke-width="1.8" />
                    <input v-model="clientSearchTerm" type="search" placeholder="Buscar no painel" @focus="showSearchSuggestions = true" />
                    <button v-if="clientSearchTerm" type="button" class="client-search-clear" aria-label="Limpar busca" @click="clientSearchTerm = ''">
                        <X :size="15" :stroke-width="2" />
                    </button>
                </label>
                <Transition name="client-profile-fade">
                    <div v-if="showSearchSuggestions" class="client-search-suggestions">
                        <p class="client-search-suggestions-title">{{ clientSearchTerm ? 'Sugestões encontradas' : 'Navegue pelo painel' }}</p>
                        <NuxtLink v-for="suggestion in clientSearchSuggestions" :key="suggestion.to" :to="suggestion.to" class="client-search-suggestion" @click="closeClientSearch">
                            <component :is="suggestion.icon" :size="18" :stroke-width="1.8" />
                            <span><strong>{{ suggestion.title }}</strong><small>{{ suggestion.description }}</small></span>
                        </NuxtLink>
                        <p v-if="!clientSearchSuggestions.length" class="client-search-empty">Nenhum resultado encontrado.</p>
                    </div>
                </Transition>
            </div>

            <div v-if="!usesProductLayout" class="layout-topbar-date hidden lg:flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-surface-700 rounded-lg">
                <CalendarDays :size="17" class="text-it-primary" />
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ new Date().toLocaleDateString('pt-BR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
            </div>

            <button v-if="isClient" type="button" class="client-notification-button" aria-label="Ver notificações" @click="handleNotificationClick">
                <img :src="`${config.app.baseURL}images/dashboard/icons/sino.svg`.replace(/\/+/g, '/')" alt="" class="client-notification-icon" />
                <span v-if="unreadAppNotifications" class="client-notification-badge"></span>
            </button>

            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content relative">
                    <button
                        type="button"
                        :class="usesProductLayout ? 'client-profile-trigger' : 'flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors'"
                        :aria-expanded="showProfileMenu"
                        @click="showProfileMenu = !showProfileMenu"
                    >
                        <img :src="userAvatar" :alt="userName" class="w-8 h-8 rounded-full object-cover" />
                        <span v-if="usesProductLayout" class="client-profile-name">{{ userName }}</span>
                        <img v-if="usesProductLayout" :src="`${config.app.baseURL}images/dashboard/icons/seta-baixo.svg`.replace(/\/+/g, '/')" alt="" class="client-profile-chevron" :class="{ 'is-open': showProfileMenu }" />
                    </button>

                    <Transition name="client-profile-fade">
                    <div v-if="showProfileMenu" :class="usesProductLayout ? 'client-profile-menu' : 'profile-menu-default'" @click.stop>
                        <div :class="usesProductLayout ? 'client-profile-summary' : 'p-4'">
                            <div :class="usesProductLayout ? 'client-profile-identity' : 'flex items-center gap-3'">
                                <img :src="userAvatar" :alt="userName" class="w-12 h-12 rounded-full object-cover" />
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white mb-0!">{{ userName }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-0! overflow-hidden text-ellipsis max-w-36.25">{{ userEmail }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-surface-700"></div>

                        <div class="p-2">
                            <NuxtLink to="/dashboard/perfil" class="flex items-center gap-3 px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-lg transition-colors" @click="closeProfileMenu">
                                <CircleUserRound :size="19" /><span class="font-medium">Ver perfil</span>
                            </NuxtLink>
                            <NuxtLink v-if="isClient" :to="{ path: '/dashboard/perfil', hash: '#alterar-senha' }" class="flex items-center gap-3 px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-lg transition-colors" @click="closeProfileMenu">
                                <LockKeyhole :size="19" class="text-gray-600 dark:text-gray-400" /><span class="font-medium">Alterar senha</span>
                            </NuxtLink>
                            <NuxtLink v-if="!isClient" :to="managementOrdersRoute" class="flex items-center gap-3 px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-lg transition-colors" @click="closeProfileMenu">
                                <List :size="19" class="text-gray-600 dark:text-gray-400" /><span class="font-medium">Central de pedidos</span>
                            </NuxtLink>
                        </div>

                        <div class="border-t border-gray-200 dark:border-surface-700"></div>
                        <div class="p-2">
                            <button class="w-full flex items-center gap-3 px-4 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" @click="openLogoutModal">
                                <LogOut :size="19" /><span class="font-medium">Sair</span>
                            </button>
                        </div>
                    </div>
                    </Transition>
                    <div v-if="showProfileMenu" class="fixed inset-0 z-40" @click="closeProfileMenu"></div>
                </div>
            </div>
        </div>
        </div>
    </header>
    <Teleport to="body">
        <Transition name="client-notifications-fade">
            <div v-show="showNotifications" class="client-notifications-layer" @click.self="showNotifications = false">
            <section v-if="isClient && showNotifications" class="client-mobile-notifications" aria-label="Notificações">
                <header>
                    <h2>Notificações</h2>
                    <button type="button" aria-label="Fechar notificações" @click="showNotifications = false"><X :size="19" /></button>
                </header>
                <TransitionGroup name="client-notification-item" tag="div" class="client-mobile-notifications-list">
                    <article v-for="notification in appNotifications" :key="notification.id" :class="{ unread: !notification.read, 'is-dismissing': dismissingNotificationIds.includes(notification.id) }">
                        <div class="client-notification-copy">
                            <strong>{{ notification.title }}</strong>
                            <p>{{ notification.message }}</p>
                        </div>
                        <button type="button" class="client-notification-check" aria-label="Marcar notificação como lida" @click="dismissNotification(notification.id)"></button>
                    </article>
                </TransitionGroup>
                <div v-if="!appNotifications.length && !delayEmptyNotifications" class="client-mobile-notifications-empty">
                    <span><img :src="`${config.app.baseURL}images/dashboard/icons/sino.svg`.replace(/\/+/g, '/')" alt="" /></span>
                    <p>Sem notificações no momento</p>
                </div>
            </section>
            </div>
        </Transition>
    </Teleport>
    <Teleport to="body">
        <Transition name="topbar-logout-fade">
            <div v-if="showLogoutModal" class="topbar-logout-backdrop" role="presentation" @click.self="closeLogoutModal">
                <section class="topbar-logout-modal" role="dialog" aria-modal="true" aria-labelledby="topbar-logout-title">
                    <button type="button" class="topbar-logout-close" aria-label="Fechar confirmação" :disabled="isLoggingOut" @click="closeLogoutModal"><X :size="20" :stroke-width="1.9" /></button>
                    <div class="topbar-logout-mark" aria-hidden="true"><LogOut :size="24" :stroke-width="1.9" /></div>
                    <h2 id="topbar-logout-title">Deseja sair da sua conta?</h2>
                    <p>Sua sessão será encerrada neste dispositivo. Será necessário fazer login novamente para acessar o painel.</p>
                    <div class="topbar-logout-actions">
                        <button type="button" class="topbar-logout-cancel" :disabled="isLoggingOut" @click="closeLogoutModal">Cancelar</button>
                        <button type="button" class="topbar-logout-confirm" :disabled="isLoggingOut" @click="confirmLogout">
                            <i v-if="isLoggingOut" class="pi pi-spin pi-spinner" aria-hidden="true"></i>
                            <LogOut v-else :size="17" :stroke-width="1.9" aria-hidden="true" />
                            <span>{{ isLoggingOut ? 'Saindo...' : 'Sim, sair' }}</span>
                        </button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
