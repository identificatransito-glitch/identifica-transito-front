<script setup>
import { computed, ref } from 'vue';
import { Package, CarFront, CircleHelp, CircleUserRound, House, List, MapPin, MoreHorizontal, Phone, Settings, Tag, Ticket, UserPlus, UsersRound } from '@lucide/vue';

const route = useRoute();
const auth = useAuth();
auth.init();
const showMore = ref(false);
const mobileNavRef = ref(null);

const isSuperAdmin = computed(() => {
    const type = auth.user.value?.type;
    return type === 'superAdmin' || type === 1 || route.path.startsWith('/dashboard/superadmin');
});

const isAdmin = computed(() => {
    const type = auth.user.value?.type;
    return type === 'admin' || type === 2 || route.path.startsWith('/dashboard/admin');
});

const role = computed(() => isSuperAdmin.value ? 'superadmin' : isAdmin.value ? 'admin' : 'client');

const clientItems = [
    { label: 'Início', to: '/dashboard', icon: House },
    { label: 'Veículos', to: '/dashboard/veiculos', icon: CarFront },
    { label: 'Etiquetas', to: '/dashboard/etiquetas', icon: Tag },
    { label: 'Pedidos', to: '/dashboard/pedidos', icon: List },
    { label: 'Perfil', to: '/dashboard/perfil', icon: CircleUserRound, more: true }
];

const adminItems = [
    { label: 'Início', to: '/dashboard/admin', icon: House },
    { label: 'Usuários', to: '/dashboard/admin/usuarios', icon: UsersRound },
    { label: 'Pedidos', to: '/dashboard/admin/pedidos', icon: List },
    { label: 'Mais', to: '', icon: MoreHorizontal, more: true }
];

const superAdminItems = [
    { label: 'Início', to: '/dashboard/superadmin', icon: House },
    { label: 'Usuários', to: '/dashboard/superadmin/usuarios', icon: UsersRound },
    { label: 'Veículos', to: '/dashboard/superadmin/veiculos', icon: CarFront },
    { label: 'Pedidos', to: '/dashboard/superadmin/pedidos', icon: List },
    { label: 'Mais', to: '', icon: MoreHorizontal, more: true }
];

const items = computed(() => role.value === 'superadmin' ? superAdminItems : role.value === 'admin' ? adminItems : clientItems);

const moreItems = computed(() => {
    if (role.value === 'superadmin') {
        return [
            { label: 'Meu perfil', to: '/dashboard/perfil', icon: CircleUserRound },
            { label: 'Etiquetas', to: '/dashboard/superadmin/etiquetas', icon: Tag },
            { label: 'Cupons de desconto', to: '/dashboard/superadmin/cupons-de-desconto', icon: Ticket },
            { label: 'Frete', to: '/dashboard/superadmin/frete', icon: Package },
            { label: 'Pontos de venda', to: '/dashboard/superadmin/pontos-de-venda', icon: MapPin },
            { label: 'FAQ', to: '/dashboard/superadmin/faq', icon: CircleHelp }
        ];
    }

    if (role.value === 'admin') {
        return [
            { label: 'Meu perfil', to: '/dashboard/perfil', icon: CircleUserRound }
        ];
    }

    return [
        { label: 'Meu perfil', to: '/dashboard/perfil', icon: CircleUserRound },
        { label: 'Suporte', to: '/dashboard/suporte', icon: CircleHelp },
        { label: 'Configurações', to: '/dashboard/configuracoes', icon: Settings },
        { label: 'Notificar um motorista', to: '/dashboard/notificacoes-motoristas', icon: Phone }
    ];
});

const isActive = (item) => {
    if (!item.to) return moreItems.value.some((entry) => route.path.startsWith(entry.to));
    if (item.to === '/dashboard' || item.to === '/dashboard/admin' || item.to === '/dashboard/superadmin') return route.path === item.to;
    return route.path.startsWith(item.to);
};

const toggleMore = () => {
    showMore.value = !showMore.value;
};

const closeMore = () => {
    showMore.value = false;
};

const closeMoreOnOutsideClick = (event) => {
    if (showMore.value && mobileNavRef.value && !mobileNavRef.value.contains(event.target)) {
        closeMore();
    }
};

onMounted(() => document.addEventListener('pointerdown', closeMoreOnOutsideClick));
onUnmounted(() => document.removeEventListener('pointerdown', closeMoreOnOutsideClick));
</script>

<template>
    <div ref="mobileNavRef">
    <nav class="client-mobile-nav" :aria-label="`Navegação principal do perfil ${role}`">
        <Transition name="client-mobile-more">
            <div v-if="showMore" class="client-mobile-more-panel" role="dialog" aria-label="Mais opções">
                <NuxtLink v-for="item in moreItems" :key="item.to" :to="item.to" class="client-mobile-more-item" @click="closeMore">
                    <component :is="item.icon" :size="20" :stroke-width="1.9" />
                    <span>{{ item.label }}</span>
                </NuxtLink>
            </div>
        </Transition>

        <div class="client-mobile-nav-content">
            <template v-for="item in items" :key="item.label">
                <button
                    v-if="item.more"
                    type="button"
                    class="client-mobile-nav-item"
                    :class="{ 'is-profile': role === 'client', 'is-active': isActive(item), 'is-open': showMore }"
                    :aria-expanded="showMore"
                    @click="toggleMore"
                >
                    <span class="client-mobile-nav-icon"><component :is="item.icon" :size="22" :stroke-width="1.9" /></span>
                    <span>{{ item.label }}</span>
                </button>
                <NuxtLink
                    v-else
                    :to="item.to"
                    class="client-mobile-nav-item"
                    :class="{ 'is-active': isActive(item) }"
                    @click="closeMore"
                >
                    <span class="client-mobile-nav-icon"><component :is="item.icon" :size="22" :stroke-width="1.9" /></span>
                    <span>{{ item.label }}</span>
                </NuxtLink>
            </template>
        </div>
    </nav>
    </div>
</template>
