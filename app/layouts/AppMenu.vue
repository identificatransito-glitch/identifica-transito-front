<script setup>
import { ref, computed } from 'vue';
import AppMenuItem from './AppMenuItem.vue';
import {
    CarFront, CircleHelp, Cog, House, Info, LayoutDashboard, Phone,
    List, MapPin, Tag, Ticket, Truck, UsersRound
} from '@lucide/vue';

const auth = useAuth();
auth.init();

const isSuperAdmin = computed(() => {
    const type = auth.user.value?.type;
    return type === 'superAdmin' || type === 1;
});

const isAdmin = computed(() => {
    const type = auth.user.value?.type;
    return type === 'admin' || type === 2;
});

const isClient = computed(() => {
    const type = auth.user.value?.type;
    return type === 'client' || type === 'cliente' || type === 3;
});

const unreadNotificationsCount = useState('driver_notifications_unread_total', () => 0);
const { $api } = useNuxtApp();

const fetchUnreadNotificationsCount = async () => {
    if (!isClient.value) return;
    try {
        const res = await $api('/notifications-drivers/unread-total');
        unreadNotificationsCount.value = Number(res?.unread_total ?? 0);
    } catch {
        unreadNotificationsCount.value = 0;
    }
};

const handleUnreadChanged = () => {
    fetchUnreadNotificationsCount();
};

const clientModel = ref([
    {
        label: 'VISÃO GERAL',
        items: [
            {
                label: 'Início',
                icon: LayoutDashboard,
                to: '/dashboard'
            }
        ]
    },
    {
        label: 'VEÍCULOS',
        items: [
            {
                label: 'Meus Veículos',
                icon: CarFront,
                to: '/dashboard/veiculos'
            },
            {
                label: 'Minhas Etiquetas',
                icon: Tag,
                to: '/dashboard/etiquetas'
            }
        ]
    },
    {
        label: 'PEDIDOS',
        items: [
            {
                label: 'Meus Pedidos',
                icon: List,
                to: '/dashboard/pedidos'
            }
        ]
    },
    {
        label: 'CONTA E SUPORTE',
        items: [
            {
                label: 'Suporte',
                icon: CircleHelp,
                to: '/dashboard/suporte'
            },
            {
                label: 'Configurações',
                icon: Cog,
                to: '/dashboard/configuracoes'
            }
        ]
    },
    {
        label: 'Projeto',
        items: [
            {
                label: 'Sobre o Projeto',
                icon: Info,
                to: '/',
                target: '_blank',
                class: 'rotated-icon'
            },
        ]
    }
]);

const superAdminModel = ref([
    {
        label: 'VISÃO GERAL',
        items: [
            {
                label: 'Início',
                icon: LayoutDashboard,
                to: '/dashboard/superadmin'
            }
        ]
    },
    {
        label: 'OPERAÇÃO',
        items: [
            {
                label: 'Pedidos',
                icon: List,
                to: '/dashboard/superadmin/pedidos'
            },
            {
                label: 'Veículos',
                icon: CarFront,
                to: '/dashboard/superadmin/veiculos'
            }
        ]
    },
    {
        label: 'REDE E ACESSOS',
        items: [
            {
                label: 'Usuários',
                icon: UsersRound,
                to: '/dashboard/superadmin/usuarios'
            },
            {
                label: 'Pontos de Venda',
                icon: MapPin,
                to: '/dashboard/superadmin/pontos-de-venda'
            }
        ]
    },
    {
        label: 'COMERCIAL',
        items: [
            {
                label: 'Etiquetas',
                icon: Tag,
                to: '/dashboard/superadmin/etiquetas'
            },
            {
                label: 'Cupom de Desconto',
                icon: Ticket,
                to: '/dashboard/superadmin/cupons-de-desconto'
            },
            {label:'Frete',icon:Truck,to:'/dashboard/superadmin/frete'}
        ]
    },
    {
        label: 'CONTEÚDO E SUPORTE',
        items: [
            {
                label: 'FAQ',
                icon: CircleHelp,
                to: '/dashboard/superadmin/faq'
            }
        ]
    },
    {
        label: 'PROJETO',
        items: [
            {
                label: 'Sobre o Projeto',
                icon: Info,
                to: '/',
                target: '_blank',
                class: 'rotated-icon'
            },
        ]
    }
]);

const adminModel = ref([
    {
        label: 'VISÃO GERAL',
        items: [
            {
                label: 'Início',
                icon: LayoutDashboard,
                to: '/dashboard/admin'
            }
        ]
    },
    {
        label: 'OPERAÇÃO',
        items: [
            {
                label: 'Pedidos',
                icon: List,
                to: '/dashboard/admin/pedidos'
            }
        ]
    },
    {
        label: 'CONSULTA DO PDV',
        items: [
            {
                label: 'Usuários',
                icon: UsersRound,
                to: '/dashboard/admin/usuarios'
            }
        ]
    },
    {
        label: 'PROJETO',
        items: [
            {
                label: 'Sobre o Projeto',
                icon: Info,
                to: '/',
                target: '_blank',
                class: 'rotated-icon'
            },
        ]
    }
]);

const model = computed(() => {
    if (isSuperAdmin.value) return superAdminModel.value;
    if (isAdmin.value) return adminModel.value;
    return clientModel.value;
});

onMounted(() => {
    fetchUnreadNotificationsCount();

    if (import.meta.client) {
        window.addEventListener('driver-notifications-unread-changed', handleUnreadChanged);
    }
});

onUnmounted(() => {
    if (import.meta.client) {
        window.removeEventListener('driver-notifications-unread-changed', handleUnreadChanged);
    }
});
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Menu items -->
        <ul class="layout-menu flex-1">
            <template v-for="(item, i) in model" :key="item">
                <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
                <li v-if="item.separator" class="menu-separator"></li>
            </template>
        </ul>
        
        <!-- Footer menu -->
        <div v-if="isClient" class="client-sidebar-footer">
            <NuxtLink to="/dashboard/notificacoes-motoristas" class="client-driver-notice">
                <span class="client-driver-notice-icon">
                    <Phone :size="20" :stroke-width="1.9" />
                </span>
                <span class="client-driver-notice-copy">
                    <strong>Notificar um motorista</strong>
                    <small>Envie um alerta direto.</small>
                </span>
            </NuxtLink>
        </div>
    </div>
</template>

<style lang="scss" scoped></style>
