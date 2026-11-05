<script setup lang="ts">
import { pdvSaveError } from '~/utils/pdvSaveError';
import PdvAccessFields from '~/components/forms/PdvAccessFields.vue';
import PdvCommissionField from '~/components/forms/PdvCommissionField.vue';
import { pdvCommissionError } from '~/utils/pdvCommission';
import PdvAddressFields from '~/components/forms/PdvAddressFields.vue';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import FormInputText from '~/components/forms/InputText.vue';
import Button from '~/components/forms/Button.vue';
import { useToast } from 'primevue/usetoast';
import InputSwitch from 'primevue/inputswitch';

definePageMeta({ layout: 'dashboard' });
useHead({ title: 'Editar Ponto de Venda - SuperAdmin | Identifica Trânsito' });

const { $api } = useNuxtApp();
const toast = useToast();
const route = useRoute();
const pickupPointId = Number(route.params.id);

const loading = ref(false);
const addressBusy = ref(false);
const addressValid = ref(true);
const loadingPoint = ref(false);
const accessEmail=ref('');
const accessPassword=ref('');
const accessConfirmation=ref('');
const accessConfigured=ref(false);

const form = ref({
    name: '',
    cnpj: '',
    address: '',
    phone: '',
    responsible: '',
    map_link: '',
    active: true,
    commission_percentage: '',
});

const fetchPickupPoint = async () => {
    loadingPoint.value = true;
    try {
        const res = await $api(`/admin/pickup-points/${pickupPointId}`) as any;
        const point = res?.data ?? res;

        accessConfigured.value=Boolean(point?.access?.configured);
        accessEmail.value=point?.access?.email ?? '';
        form.value.name = point?.name ?? '';
        form.value.cnpj = point?.cnpj ?? '';
        form.value.address = point?.address ?? '';
        form.value.phone = point?.phone ?? '';
        form.value.responsible = point?.responsible ?? '';
        form.value.map_link = point?.map_link ?? '';
        form.value.active = Boolean(point?.active);
        form.value.commission_percentage = point?.commission_percentage == null ? '' : String(point.commission_percentage);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível carregar os dados do ponto de venda.', life: 5000 });
        navigateTo('/dashboard/superadmin/pontos-de-venda');
    } finally {
        loadingPoint.value = false;
    }
};

onMounted(() => fetchPickupPoint());

const handleSubmit = async () => {
    if (addressBusy.value || !addressValid.value) {
        toast.add({ severity: 'warn', summary: 'Confira o endereço', detail: addressBusy.value ? 'Aguarde a consulta do CEP.' : 'Confira o CEP e o tamanho do endereço.', life: 4000 });
        return;
    }
    if (!form.value.name) {
        toast.add({ severity: 'error', summary: 'Campos obrigatórios', detail: 'Preencha o nome do ponto de venda.', life: 3000 });
        return;
    }

    if (!accessEmail.value.trim() || !form.value.responsible.trim() || (!accessConfigured.value && accessPassword.value.length < 8) || ((accessPassword.value || accessConfirmation.value) && (accessPassword.value.length < 8 || accessPassword.value !== accessConfirmation.value))) {
        toast.add({ severity: 'warn', summary: 'Confira o acesso do PDV', detail: 'Informe responsável e e-mail. Para criar ou trocar a senha, use pelo menos 8 caracteres e confirme a mesma senha.', life: 5000 });
        return;
    }
    const commissionError = pdvCommissionError(form.value.commission_percentage);
    if (commissionError) {
        toast.add({ severity: 'warn', summary: 'Confira o repasse', detail: commissionError, life: 4000 });
        return;
    }
    loading.value = true;
    try {
        await $api(`/admin/pickup-points/${pickupPointId}`, {
            method: 'PUT',
            body: {
                access_email: accessEmail.value.trim().toLowerCase(),
                ...(accessPassword.value ? { password: accessPassword.value, password_confirmation: accessConfirmation.value } : {}),
                name: form.value.name,
                cnpj: form.value.cnpj || null,
                address: form.value.address || null,
                phone: form.value.phone || null,
                responsible: form.value.responsible || null,
                map_link: form.value.map_link || null,
                active: form.value.active,
                commission_percentage: form.value.commission_percentage.trim() === '' ? null : Number(form.value.commission_percentage),
            },
        });
        accessPassword.value=''; accessConfirmation.value='';
        toast.add({ severity: 'success', summary: 'Ponto atualizado!', detail: 'Os dados foram salvos com sucesso.', life: 3000 });
        navigateTo('/dashboard/superadmin/pontos-de-venda');
    } catch (error: any) {
        const msg = pdvSaveError(error);
        toast.add({ severity: 'error', summary: 'Erro ao salvar', detail: msg, life: 5000 });
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="admin-page space-y-6 md:space-y-7">
        <HeroSection
            title="Editar Ponto de Venda"
            subtitle="Atualize os dados e as condições de atendimento deste ponto de venda."
            greeting="Rede de atendimento"
            :showButton="true"
            buttonLabel="Voltar para Pontos de Venda"
            buttonLink="/dashboard/superadmin/pontos-de-venda"
            buttonIcon="pi pi-arrow-left"
        />

        <div class="w-full">
            <div v-if="loadingPoint" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 space-y-6">
                <Skeleton width="45%" height="2rem" class="mb-4" />
                <div class="grid grid-cols-2 gap-6">
                    <Skeleton height="60px" />
                    <Skeleton height="60px" />
                    <Skeleton height="60px" />
                    <Skeleton height="60px" />
                </div>
                <Skeleton height="60px" />
            </div>

            <div v-else class="admin-user-form-shell pdv-form-shell bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                <div class="mb-8">
                    <h1 class="text-3xl! font-bold text-it-primary mb-2">Editar Ponto de Venda</h1>
                    <p class="text-gray-600">Atualize os campos desejados.</p>
                </div>

                <form @submit.prevent="handleSubmit" class="admin-user-form-grid space-y-8">
                    <PdvAccessFields v-model:email="accessEmail" v-model:password="accessPassword" v-model:confirmation="accessConfirmation" :configured="accessConfigured" editing :disabled="loading" />
                    <PdvCommissionField v-model="form.commission_percentage" :disabled="loading" />
                    <div class="space-y-5">
                        <h2 class="flex items-center gap-3 text-lg! font-semibold text-gray-900 border-b border-gray-100 pb-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#eef2ff] text-[#1f46ee]"><i class="pi pi-shop"></i></span>Informações do Ponto</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FormInputText
                                v-model="form.name"
                                id="name"
                                label="Nome *"
                                placeholder="Ex: Posto Shell Centro"
                                showIcon
                                icon="pi pi-shop"
                                wrapper-class="w-full"
                                inputClass="w-full"
                                :disabled="loading"
                            />
                            <FormInputText
                                v-model="form.cnpj"
                                id="cnpj"
                                label="CNPJ"
                                placeholder="00.000.000/0000-00"
                                mask="99.999.999/9999-99"
                                showIcon
                                icon="pi pi-id-card"
                                wrapper-class="w-full"
                                inputClass="w-full"
                                :disabled="loading"
                            />
                        </div>

                        <PdvAddressFields v-model="form.address" :disabled="loading" @busy="addressBusy = $event" @validity="addressValid = $event" />

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FormInputText
                                v-model="form.phone"
                                id="phone"
                                label="Telefone"
                                placeholder="(00) 00000-0000"
                                mask="(99) 99999-9999"
                                showIcon
                                icon="pi pi-phone"
                                wrapper-class="w-full"
                                inputClass="w-full"
                                :disabled="loading"
                            />
                            <FormInputText
                                v-model="form.responsible"
                                id="responsible"
                                label="Responsável *"
                                placeholder="Ex: Frentista Rubens"
                                showIcon
                                icon="pi pi-user"
                                wrapper-class="w-full"
                                inputClass="w-full"
                                :disabled="loading"
                            />
                        </div>

                        <FormInputText
                            v-model="form.map_link"
                            id="map_link"
                            type="url"
                            label="Link do Mapa"
                            placeholder="https://maps.google.com/..."
                            showIcon
                            icon="pi pi-map"
                            wrapper-class="w-full"
                            inputClass="w-full"
                            :disabled="loading"
                        />

                    </div>

                    <div class="space-y-5">
                        <h2 class="flex items-center gap-3 text-lg! font-semibold text-gray-900 border-b border-gray-100 pb-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#eef2ff] text-[#1f46ee]"><i class="pi pi-verified"></i></span>Status do Ponto</h2>
                        <p class="text-sm text-gray-500">Defina se este ponto de venda ficará disponível para atendimento e retirada.</p>

                        <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3.5">
                            <div>
                                <strong class="block text-[14px] font-semibold text-gray-900 md:text-[14.5px]" style="font-family: 'Figtree', sans-serif;">Ponto ativo</strong>
                                <small class="text-gray-500">Permitir operações neste local.</small>
                            </div>
                            <InputSwitch v-model="form.active" :disabled="loading" />
                        </div>
                    </div>

                    <Button type="submit" fullWidth :loading="loading" :disabled="addressBusy || !addressValid" size="lg">
                        <i v-if="!loading" class="pi pi-check"></i>
                        Salvar Alterações
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
:global(.layout-admin .admin-page .pdv-form-shell.pdv-form-shell input:not([type='checkbox']):not([type='radio']):focus),
:global(.layout-admin .admin-page .pdv-form-shell.pdv-form-shell .p-select:focus-within) {
    border: 1px solid #64748b !important;
    outline: none !important;
    box-shadow: none !important;
}
</style>
