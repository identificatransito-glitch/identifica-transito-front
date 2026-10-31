<script setup lang="ts">
    import { computed, onMounted, ref } from 'vue';
    import UserOverview from '~/components/dashboard/UserOverview.vue';
    // throw createError({ statusCode: 500, statusMessage: 'Testando erro 500' })

    interface DashboardOrder {
        id: number | string;
        tag_name?: string;
        mp_payment_id?: number | string;
        vehicle?: { name?: string | null } | null;
        shipment?: { status?: number | string | null } | null;
    }

    definePageMeta({
        layout: 'dashboard',
    });

    // Redirect SuperAdmin to their own dashboard
    const _auth = useAuth();
    _auth.init();
    const _type = _auth.user.value?.type;
    if (_type === 'superAdmin' || _type === 1) {
        await navigateTo('/dashboard/superadmin', { replace: true });
    }

  useHead({
    title: 'Dashboard - Identifica Trânsito',
    meta: [
      { name: 'description', content: 'Visão geral do seu painel de controle.' }
    ]
  });

    // Dados de contagem
    const stats = ref({
        vehicles: 0,
        tags: 0
    });
    
    const loading = ref(false);
    const loadingRecentOrders = ref(false);

    // Últimos pedidos
    const recentOrders = ref<Array<{ id: string; title: string; status: 'em-andamento' | 'em-rota' | 'entregue' | 'cancelado' }>>([]);

    const mapShipmentStatusToCardStatus = (status?: number | string | null): 'em-andamento' | 'em-rota' | 'entregue' | 'cancelado' => {
        const statusKey = String(status ?? '').toLowerCase();

        if (['4', 'entregue', 'delivered'].includes(statusKey)) return 'entregue';
        if (['5', '6', 'failed', 'devolvido', 'returned', 'cancelled', 'cancelado'].includes(statusKey)) return 'cancelado';
        if (['2', '3', 'in_transit', 'out_for_delivery', 'em trânsito', 'em transito'].includes(statusKey)) return 'em-rota';
        return 'em-andamento';
    };

    const heroStats = computed(() => ({
        vehicles: {
            label: 'Veículos ativos',
            count: String(stats.value.vehicles)
        },
        orders: {
            label: 'Etiquetas cadastradas',
            count: String(stats.value.tags)
        }
    }));

    const auth = useAuth();
    auth.init();
    const userName = computed(() => auth.user.value?.name || '');

    const fetchVehiclesCount = async () => {
        try {
            loading.value = true;
            const { $api } = useNuxtApp();
            const response = await $api('/vehicles/count');
            const data = (response as any)?.data ?? response;
            const count = typeof data === 'number' ? data : data?.count;
            if (typeof count === 'number') {
                stats.value.vehicles = count;
            }
        } catch (error) {
            console.error('Erro ao buscar contagem de veículos:', error);
        } finally {
            loading.value = false;
        }
    };

    const fetchTagPurchasesCount = async () => {
        try {
            const { $api } = useNuxtApp();
            const response = await $api('/tag-purchases/count');
            const data = (response as any)?.data ?? response;
            const total = typeof data?.total === 'number' ? data.total : Number(data?.total ?? 0);

            if (!Number.isNaN(total)) {
                stats.value.tags = total;
            }
        } catch (error) {
            console.error('Erro ao buscar total de etiquetas cadastradas:', error);
        }
    };

    const fetchRecentOrders = async () => {
        loadingRecentOrders.value = true;
        try {
            const { $api } = useNuxtApp();
            const response = await $api('/orders?page=1&per_page=4') as any;
            const payload = response?.data?.data ? response.data : response;
            const data = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);

            recentOrders.value = (data as DashboardOrder[]).slice(0, 4).map((order) => ({
                id: `#PED-${order.mp_payment_id ?? order.id}`,
                title: order.tag_name
                    ? `${order.tag_name} - 1 unidade`
                    : (order.vehicle?.name || `Pedido ${order.id}`),
                status: mapShipmentStatusToCardStatus(order.shipment?.status)
            }));
        } catch (error) {
            console.error('Erro ao carregar últimos pedidos:', error);
            recentOrders.value = [];
        } finally {
            loadingRecentOrders.value = false;
        }
    };

    onMounted(() => {
        fetchVehiclesCount();
        fetchTagPurchasesCount();
        fetchRecentOrders();
    });
</script>

<template>
    <UserOverview :user-name="userName" :stats="stats" :loading="loading" :loading-recent-orders="loadingRecentOrders" :recent-orders="recentOrders" />
</template>
