<script setup lang="ts">
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
useHead({ title: 'Novo Ponto de Venda - SuperAdmin | Identifica Trânsito' });

const { $api } = useNuxtApp();
const toast = useToast();
const loading = ref(false);
const addressBusy = ref(false);
const addressValid = ref(true);
const accessEmail = ref('');
const accessPassword = ref('');
const accessConfirmation = ref('');


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

const handleSubmit = async () => {
    if (addressBusy.value || !addressValid.value) {
        toast.add({ severity: 'warn', summary: 'Confira o endereço', detail: addressBusy.value ? 'Aguarde a consulta do CEP.' : 'Confira o CEP e o tamanho do endereço.', life: 4000 });
        return;
    }
    if (!form.value.name) {
        toast.add({ severity: 'error', summary: 'Campos obrigatórios', detail: 'Preencha o nome do ponto de venda.', life: 3000 });
        return;
    }

    if (!accessEmail.value.trim() || !form.value.responsible.trim() || accessPassword.value.length < 8 || accessPassword.value !== accessConfirmation.value) {
        toast.add({ severity: 'warn', summary: 'Confira o acesso do PDV', detail: 'Informe o responsável, o e-mail e uma senha com pelo menos 8 caracteres. A confirmação deve ser igual à senha.', life: 5000 });
        return;
    }
    const commissionError = pdvCommissionError(form.value.commission_percentage);
    if (commissionError) {
        toast.add({ severity: 'warn', summary: 'Confira o repasse', detail: commissionError, life: 4000 });
        return;
    }
    loading.value = true;
    try {
        await $api('/admin/pickup-points', {
            method: 'POST',
            body: {
                access_email: accessEmail.value.trim().toLowerCase(), password: accessPassword.value, password_confirmation: accessConfirmation.value,
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
        accessPassword.value = ''; accessConfirmation.value = '';
        toast.add({ severity: 'success', summary: 'Ponto de venda criado!', detail: 'O ponto de venda e seu acesso de administrador foram cadastrados.', life: 3000 });
        navigateTo('/dashboard/superadmin/pontos-de-venda');
    } catch (error: any) {
        const msg = error?.data?.message || error?.data?.error || 'Não foi possível cadastrar o ponto de venda.';
        toast.add({ severity: 'error', summary: 'Erro ao cadastrar', detail: msg, life: 5000 });
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="admin-page space-y-6 md:space-y-7">
        <HeroSection
            title="Novo Ponto de Venda"
            subtitle="Adicione um novo local de venda ou retirada à sua rede."
            greeting="Expansão da rede"
            :showButton="true"
            buttonLabel="Voltar para Pontos de Venda"
            buttonLink="/dashboard/superadmin/pontos-de-venda"
            buttonIcon="pi pi-arrow-left"
        />

        <div class="w-full">
            <div class="admin-user-form-shell pdv-form-shell bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                <div class="mb-8">
                    <h1 class="text-3xl! font-bold text-it-primary mb-2">Cadastrar Ponto de Venda</h1>
                    <p class="text-gray-600">Preencha as informações para criar um novo ponto de venda.</p>
                </div>

                <form @submit.prevent="handleSubmit" class="admin-user-form-grid space-y-8">
                    <PdvAccessFields v-model:email="accessEmail" v-model:password="accessPassword" v-model:confirmation="accessConfirmation" :disabled="loading" />
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
                        Cadastrar Ponto de Venda
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
