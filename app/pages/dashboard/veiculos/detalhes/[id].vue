<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { onBeforeRouteUpdate } from 'vue-router';
import { CalendarDays, CarFront, List, MapPin, Pencil, ShoppingBag, Tag, Trash2, UserRound } from '@lucide/vue';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import VehicleKitCard from '~/components/dashboard/VehicleKitCard.vue';
import { useVehicleActions } from '~/composables/useVehicleActions';
import { useToast } from 'primevue/usetoast';
import { getUsageProfileLabel, getVehicleTypeLabel } from '~/utils/vehicleEnums';
import Skeleton from 'primevue/skeleton';
import { redirectStatus } from '~/utils/general';

definePageMeta({
    layout: 'dashboard'
});

useHead({
    title: 'Detalhes do Veículo - Identifica Trânsito',
    meta: [
        { name: 'description', content: 'Visualize os detalhes de seu veículo e etiqueta de identificação.' }
    ]
});

const route = useRoute();
const { confirmDelete } = useVehicleActions();

const config = useRuntimeConfig();
const assetWithBase = (path: string) => {
    if (path.startsWith('data:') || path.startsWith('http')) return path;
    return `${config.app.baseURL}${path}`.replace(/\/+/g, '/').replace(':/', '://');
};
const toast = useToast();

const vehicleData = ref({
    id: route.params.id || '',
    placa: '',
    tipo: '',
    registro: '',
    perfilUso: '',
    dataCadastro: '',
    etiqueta: {
        codigo: '',
        tipo: 'Indisponível',
        imagem: assetWithBase('/images/dashboard/etiquetas/azul.svg')
    }
});

const loading = ref(false);
const rawVehicle = ref<Record<string, any> | null>(null);
const canPurchaseTag = computed(() => !!rawVehicle.value?.can_purchase_tag);

const mapVehicleData = (vehicle: Record<string, any>) => {
    const registro = [vehicle.register_city, vehicle.register_state].filter(Boolean).join(' - ');
    return {
        id: vehicle.id,
        placa: vehicle.plate || '',
        tipo: getVehicleTypeLabel(vehicle.type) || String(vehicle.type || ''),
        registro,
        perfilUso: getUsageProfileLabel(vehicle.usage_profile) || String(vehicle.usage_profile || ''),
        dataCadastro: vehicle.created_at || '',
        etiqueta: vehicle.etiqueta || {
            codigo: 'PIT' + vehicle.id,
            tipo: (getVehicleTypeLabel(vehicle.type) || vehicle.type_label || 'Indisponível').toUpperCase(),
            imagem: vehicle.tag_color
                ? assetWithBase(`/images/dashboard/etiquetas/${vehicle.tag_color}.svg`)
                : assetWithBase('/images/dashboard/etiquetas/azul.svg')
        }
    };
};

const getRouteId = (params: Record<string, any> = route.params) => {
    const id = params.id;
    return Array.isArray(id) ? id[0] : id;
};

const fetchVehicleDetails = async (id?: string | string[]) => {
    const vehicleId = Array.isArray(id) ? id[0] : id || getRouteId();
    if (!vehicleId) return;

    loading.value = true;
    try {
        const { $api } = useNuxtApp();
        const response = await $api(`/vehicles/${vehicleId}`) as any;
        const vehicle = response?.data?.data || response?.data?.vehicle || response?.data || response;
        rawVehicle.value = vehicle;
        vehicleData.value = mapVehicleData(vehicle);
    } catch (error: any) {
        redirectStatus(error?.statusCode);
        const apiMessage = error?.data?.message || error?.data?.error;
        const errorMsg = apiMessage || error?.message || 'Não foi possível carregar o veículo.';
        toast.add({
            severity: 'error',
            summary: 'Erro',
            detail: errorMsg,
            life: 5000
        });
    } finally {
        loading.value = false;
    }
};

const handleExcluirVeiculo = () => {
    confirmDelete(
        vehicleData.value.id,
        vehicleData.value.placa,
        () => {
            navigateTo('/dashboard/veiculos');
        }
    );
};

const handleEditar = () => {
    navigateTo(`/dashboard/veiculos/editar/${vehicleData.value.id}`);
};

const handleComprarEtiqueta = () => {
    if (!rawVehicle.value) return;
    const selectedVehicles = useState('selectedVehicles');
    selectedVehicles.value = [rawVehicle.value];
    navigateTo('/dashboard/pagamento');
};

onMounted(() => {
    fetchVehicleDetails();
});

onBeforeRouteUpdate((to) => {
    fetchVehicleDetails(to.params.id as string | string[]);
});
</script>

<template>
    <div class="space-y-6 md:space-y-7">
        <!-- Hero -->
        <HeroSection
            title="Detalhes do veículo"
            subtitle="Consulte os dados e as etiquetas vinculadas a este veículo."
            :showButton="true"
            buttonLabel="Voltar para meus veículos"
            buttonLinkBack
            buttonIcon="pi pi-arrow-left"
        />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div class="mb-6 flex h-10 items-center justify-between gap-4">
                    <div class="flex h-10 items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#eaf0ff] text-[#1f46ee]">
                            <CarFront :size="20" :stroke-width="1.9" aria-hidden="true" />
                        </div>
                        <h2 class="relative -top-3 leading-none text-xl! font-semibold text-[#172b4d] mb-0!">Informações do veículo</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 transition-colors hover:border-[#1f46ee] hover:bg-[#eef2ff] hover:text-[#1f46ee]" aria-label="Editar veículo" title="Editar veículo" @click="handleEditar">
                            <Pencil :size="17" :stroke-width="2" aria-hidden="true" />
                        </button>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 bg-white text-red-700 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Excluir veículo" title="Excluir veículo" :disabled="loading" @click="handleExcluirVeiculo">
                            <Trash2 :size="17" :stroke-width="2" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <article v-for="item in [
                        { label: 'Placa', value: vehicleData.placa, icon: CarFront, uppercase: true },
                        { label: 'Tipo do veículo', value: vehicleData.tipo, icon: List },
                        { label: 'Registro', value: vehicleData.registro, icon: MapPin },
                        { label: 'Perfil de uso', value: vehicleData.perfilUso, icon: UserRound },
                        { label: 'Data de cadastro', value: vehicleData.dataCadastro, icon: CalendarDays }
                    ]" :key="item.label" class="flex min-h-[82px] items-center gap-3 rounded-xl border border-slate-200 bg-[#fafafa] px-4 py-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500">
                            <component :is="item.icon" :size="18" :stroke-width="1.8" aria-hidden="true" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 mb-1">{{ item.label }}</p>
                            <Skeleton v-if="loading" width="7rem" height="15px" />
                            <p v-else :class="{ 'uppercase': item.uppercase }" class="truncate text-base font-semibold leading-tight text-[#172b4d] mb-0!">{{ item.value || '—' }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <VehicleKitCard :image="vehicleData.etiqueta.imagem" :type="vehicleData.etiqueta.tipo" :code="vehicleData.etiqueta.codigo" :plate="vehicleData.placa" :loading="loading" :can-purchase-tag="canPurchaseTag" @purchase="handleComprarEtiqueta" />
        </div>
    </div>
</template>

<style scoped>
/* Estilos adicionais se necessário */
</style>
