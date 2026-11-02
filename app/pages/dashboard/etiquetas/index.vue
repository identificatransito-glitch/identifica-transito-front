<script setup lang="ts">
import { computed, ref } from 'vue';
import { CarFront, Check, PackageCheck, Plus, ShoppingBag, Tag } from '@lucide/vue';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import VehicleTagCard from '~/components/dashboard/VehicleTagCard.vue';

definePageMeta({ layout: 'dashboard' });

useHead({
    title: 'Minhas Etiquetas - Identifica Trânsito',
    meta: [{ name: 'description', content: 'Selecione e compre etiquetas para seus veículos.' }]
});

interface Vehicle {
    id: number;
    user_id: number;
    plate: string;
    name: string;
    year: string;
    color: string;
    type: number;
    type_label: string;
    register_state: string;
    register_city: string;
    usage_profile: number;
    usage_profile_label: string;
    tag_color: string;
    tag_price: string;
    tag_purchases: any[];
    created_at: string;
    updated_at: string;
    can_purchase_tag: boolean;
    selected?: boolean;
}

const vehicles = ref<Vehicle[]>([]);
const loading = ref(false);
const toast = useToast();
const config = useRuntimeConfig();

const assetWithBase = (path: string) => {
    if (path.startsWith('data:') || path.startsWith('http')) return path;
    return `${config.app.baseURL}${path}`.replace(/\/+/g, '/').replace(':/', '://');
};

const getTagImage = (tagColor: string) => assetWithBase(tagColor ? `/images/dashboard/etiquetas-v2/${tagColor}.svg` : '/images/dashboard/etiquetas-v2/azul.svg');

const fetchVehicles = async () => {
    loading.value = true;
    try {
        const { $api } = useNuxtApp();
        const response = await $api('/vehicles');
        const data = response?.data || response;
        vehicles.value = Array.isArray(data) ? data : data?.data || [];
    } catch (error: any) {
        const apiMessage = error?.data?.message || error?.data?.error;
        toast.add({
            severity: 'error',
            summary: 'Erro ao carregar',
            detail: apiMessage || error?.message || 'Não foi possível carregar os veículos.',
            life: 5000
        });
    } finally {
        loading.value = false;
    }
};

onMounted(fetchVehicles);

const totalSelected = computed(() => vehicles.value.filter((vehicle) => vehicle.selected).length);
const totalPrice = computed(() => vehicles.value.filter((vehicle) => vehicle.selected).reduce((sum, vehicle) => sum + Number.parseFloat(vehicle.tag_price || '0'), 0));
const purchasableVehicles = computed(() => vehicles.value.filter((vehicle) => vehicle.can_purchase_tag));
const allPurchasableSelected = computed(() => purchasableVehicles.value.length > 0 && purchasableVehicles.value.every((vehicle) => vehicle.selected));

const selectAll = () => {
    purchasableVehicles.value.forEach((vehicle) => { vehicle.selected = !allPurchasableSelected.value; });
};

const toggleSelection = (id: number) => {
    const vehicle = vehicles.value.find((item) => item.id === id);
    if (vehicle?.can_purchase_tag) vehicle.selected = !vehicle.selected;
};

const handleComprar = () => {
    if (!totalSelected.value) {
        toast.add({ severity: 'warn', summary: 'Selecione um veículo', detail: 'Selecione pelo menos um veículo para comprar etiquetas.', life: 3000 });
        return;
    }

    const selectedVehiclesState = useState('selectedVehicles');
    selectedVehiclesState.value = vehicles.value.filter((vehicle) => vehicle.selected);
    navigateTo('/dashboard/pagamento');
};
</script>

<template>
    <div class="space-y-6 md:space-y-7">
        <HeroSection
            greeting="Etiquetas de Identificação Veicular"
            :greeting-icon="Tag"
            title="Compre etiquetas"
            subtitle="Selecione os veículos e monte seu kit de identificação."
            :showButton="true"
            buttonLabel="Meus veículos"
            buttonLink="/dashboard/veiculos"
            buttonIcon="pi pi-arrow-left"
        />

        <div v-if="loading" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Skeleton v-for="index in 3" :key="index" height="18rem" class="w-full rounded-xl" />
        </div>

        <section v-else-if="vehicles.length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-slate-200 bg-white p-8 text-center sm:p-12">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#eaf0ff] text-[#1f46ee]">
                <CarFront :size="31" :stroke-width="1.8" aria-hidden="true" />
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#1f46ee]">Nenhum veículo disponível</p>
            <h2 class="mt-2 text-2xl! font-semibold text-[#172b4d] mb-0!">Cadastre um veículo primeiro</h2>
            <p class="mt-3 max-w-md text-slate-600">Depois você poderá escolher o kit de etiquetas ideal para ele.</p>
            <NuxtLink to="/dashboard/veiculos/novo" class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-[#1f46ee] px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#1739d4]">
                <Plus :size="18" :stroke-width="2.2" aria-hidden="true" />
                Cadastrar veículo
            </NuxtLink>
        </section>

        <template v-else>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eaf0ff] text-[#1f46ee]">
                            <Tag :size="19" :stroke-width="1.9" aria-hidden="true" />
                        </div>
                        <h2 class="relative -top-3 leading-none text-xl! font-semibold text-[#172b4d] mb-0!">Escolha os veículos</h2>
                    </div>
                    <label class="inline-flex cursor-pointer items-center gap-2 self-end text-sm font-semibold text-[#172b4d] sm:self-auto">
                        <input type="checkbox" :checked="allPurchasableSelected" :disabled="!purchasableVehicles.length" class="h-4 w-4 rounded border-slate-300 text-[#1f46ee] focus:ring-[#1f46ee] disabled:cursor-not-allowed" @change="selectAll" />
                        Selecionar todos
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <VehicleTagCard v-for="vehicle in vehicles" :key="vehicle.id" :vehicle="vehicle" :get-tag-image="getTagImage" @toggle-selection="toggleSelection" />
                </div>
            </section>

            <section
                class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-[#fafafa] p-4 sm:p-5 md:flex-row md:items-center md:justify-between"
                :class="{
                    'purchase-summary--fixed max-[1000px]:fixed max-[1000px]:inset-x-0 max-[1000px]:bottom-[calc(4.4375rem+env(safe-area-inset-bottom))] max-[1000px]:z-[998] max-[1000px]:rounded-none max-[1000px]:border-x-0 max-[1000px]:border-b-0 max-[1000px]:px-5 max-[1000px]:py-4 max-[1000px]:shadow-[0_-10px_30px_rgba(15,23,42,0.16)]': totalSelected
                }"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eaf0ff] text-[#1f46ee]">
                        <ShoppingBag :size="19" :stroke-width="1.9" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-[#172b4d] mb-1">{{ totalSelected ? `${totalSelected} ${totalSelected === 1 ? 'veículo selecionado' : 'veículos selecionados'}` : 'Nenhum veículo selecionado' }}</p>
                        <p class="text-sm text-slate-600 mb-0!">{{ totalSelected ? `Total do kit: R$ ${totalPrice.toFixed(2)}` : 'Escolha um veículo para continuar.' }}</p>
                    </div>
                </div>
                <button type="button" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-[#1f46ee] px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#1739d4] disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="!totalSelected" @click="handleComprar">
                    <PackageCheck :size="18" :stroke-width="2.1" aria-hidden="true" />
                    Continuar compra
                </button>
            </section>
            <div v-if="totalSelected" class="hidden h-40 max-[1000px]:block" aria-hidden="true" />
        </template>
    </div>
</template>
