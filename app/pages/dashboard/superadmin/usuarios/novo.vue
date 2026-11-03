<script setup lang="ts">
import HeroSection from '~/components/dashboard/HeroSection.vue';
import FormInputText from '~/components/forms/InputText.vue';
import InputPassword from '~/components/forms/InputPassword.vue';
import PasswordStrengthIndicator from '~/components/forms/PasswordStrengthIndicator.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import Button from '~/components/forms/Button.vue';
import { useToast } from 'primevue/usetoast';
import { BIOLOGICAL_SEX_OPTIONS } from '~/utils/userBiologicalSex';

definePageMeta({ layout: 'dashboard' });
useHead({ title: 'Novo Usuário - SuperAdmin | Identifica Trânsito' });

const { $api } = useNuxtApp();
const toast = useToast();
const loading = ref(false);

const form = ref({
    name: '',
    email: '',
    password: '',
    cpf: '',
    birth_date: '',
    phone: '',
    gender: '',
    type: null as number | null,
    pdv_id: null as number | null,
});

const TIPOS_USUARIO = [
    { label: 'Cliente', value: 3 },
    { label: 'Admin', value: 2 },
];


const pointsOfSale = ref<Array<{ label: string; value: number }>>([]);
const loadingPdv = ref(false);

const fetchPointsOfSale = async () => {
    loadingPdv.value = true;
    try {
        const res = await $api('/points-of-sale') as any;
        const data = res?.data ?? res;
        pointsOfSale.value = (Array.isArray(data) ? data : []).map((p: any) => ({
            label: p.name,
            value: p.id,
        }));
    } catch (e) {
        console.error('Erro ao carregar pontos de venda:', e);
    } finally {
        loadingPdv.value = false;
    }
};

onMounted(() => fetchPointsOfSale());


const handleSubmit = async () => {
    if (!form.value.name || !form.value.email || !form.value.password || !form.value.gender || !form.value.type) {
        toast.add({ severity: 'error', summary: 'Campos obrigatórios', detail: 'Preencha nome, e-mail, senha, sexo biológico e tipo.', life: 3000 });
        return;
    }


    loading.value = true;
    try {
        const body: Record<string, any> = {
            name: form.value.name,
            email: form.value.email,
            password: form.value.password,
            type: form.value.type,
            cpf: form.value.cpf || null,
            birth_date: form.value.birth_date || null,
            phone: form.value.phone || null,
            gender: form.value.gender || null,
            pdv_id: form.value.pdv_id || null,
        };


        await $api('/admin/users', { method: 'POST', body });
        toast.add({ severity: 'success', summary: 'Usuário cadastrado!', detail: 'O usuário foi criado com sucesso.', life: 3000 });
        navigateTo('/dashboard/superadmin/usuarios');
    } catch (error: any) {
        const msg = error?.data?.message || error?.data?.error || 'Não foi possível cadastrar o usuário.';
        toast.add({ severity: 'error', summary: 'Erro ao cadastrar', detail: msg, life: 5000 });
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="admin-page space-y-6 md:space-y-7">
        <HeroSection
            title="Novo Usuário"
            subtitle="Crie um novo acesso e defina o perfil adequado para a operação."
            greeting="Novo acesso"
            :showButton="true"
            buttonLabel="Voltar para Usuários"
            buttonLink="/dashboard/superadmin/usuarios"
            buttonIcon="pi pi-arrow-left"
        />

        <div class="max-w-3xl mx-auto">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                <div class="mb-8">
                    <h1 class="text-3xl! font-bold text-it-primary mb-2">Cadastrar Novo Usuário</h1>
                    <p class="text-gray-600">Preencha as informações para criar um novo usuário na plataforma.</p>
                </div>

                <form @submit.prevent="handleSubmit" class="space-y-8">
                    <!-- Dados Pessoais -->
                    <div class="space-y-5">
                        <h2 class="text-lg! font-semibold text-gray-900 border-b border-gray-100 pb-3">Dados Pessoais</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FormInputText
                                v-model="form.name"
                                id="name"
                                label="Nome completo"
                                placeholder="Nome do usuário"
                                showIcon
                                icon="pi pi-user"
                                wrapper-class="w-full"
                                inputClass="w-full"
                                :disabled="loading"
                                required
                            />
                            <FormInputText
                                v-model="form.email"
                                id="email"
                                type="email"
                                label="E-mail"
                                placeholder="email@exemplo.com"
                                showIcon
                                icon="pi pi-envelope"
                                wrapper-class="w-full"
                                inputClass="w-full"
                                :disabled="loading"
                                required
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FormInputText
                                v-model="form.cpf"
                                id="cpf"
                                label="CPF"
                                placeholder="000.000.000-00"
                                mask="999.999.999-99"
                                showIcon
                                icon="pi pi-id-card"
                                wrapper-class="w-full"
                                inputClass="w-full"
                                :disabled="loading"
                            />
                            <SelectInput
                                v-model="form.gender"
                                id="gender"
                                label="Sexo Biológico"
                                :options="BIOLOGICAL_SEX_OPTIONS"
                                placeholder="Selecione"
                                icon="pi pi-user"
                                wrapperClass="w-full"
                                :disabled="loading"
                                required
                            />
                        </div>

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
                                required
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FormInputText
                                v-model="form.birth_date"
                                id="birth_date"
                                type="date"
                                label="Data de Nascimento"
                                showIcon
                                icon="pi pi-calendar"
                                wrapper-class="w-full"
                                inputClass="w-full"
                                :disabled="loading"
                                required
                            />
                        </div>
                    </div>

                    <div class="border-t border-gray-200"></div>

                    <!-- Acesso -->
                    <div class="space-y-5">
                        <h2 class="text-lg! font-semibold text-gray-900 border-b border-gray-100 pb-3">Acesso</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputPassword
                                    v-model="form.password"
                                    id="password"
                                    label="Senha"
                                    placeholder="Senha de acesso"
                                    autocomplete="new-password"
                                    wrapper-class="w-full"
                                    inputClass="w-full"
                                    :disabled="loading"
                                />
                                <PasswordStrengthIndicator :password="form.password" />
                            </div>
                            <SelectInput
                                v-model="form.type"
                                id="type"
                                label="Tipo de Usuário"
                                required
                                :options="TIPOS_USUARIO"
                                placeholder="Selecione o tipo"
                                optionLabel="label"
                                optionValue="value"
                                icon="pi pi-shield"
                                wrapperClass="w-full"
                                :disabled="loading"
                            />
                        </div>
                    </div>

                    <template v-if="form.type === 2">
                    <div class="border-t border-gray-200"></div>

                    <!-- Ponto de Venda -->
                    <div class="space-y-5">
                        <h2 class="text-lg! font-semibold text-gray-900 border-b border-gray-100 pb-3">Ponto de Venda / Coleta</h2>
                        <p class="text-sm text-gray-500 -mt-2">Opcional. Vincule este usuário a um ponto de venda.</p>

                        <SelectInput
                            v-model="form.pdv_id"
                            id="pdv_id"
                            label="Ponto de Venda"
                            :options="pointsOfSale"
                            placeholder="Selecione o ponto de venda (opcional)"
                            optionLabel="label"
                            optionValue="value"
                            icon="pi pi-shop"
                            wrapperClass="w-full"
                            :showClear="true"
                            :filter="true"
                            filterPlaceholder="Buscar ponto de venda..."
                            :disabled="loading || loadingPdv"
                        />
                        <p class="rounded-xl bg-[#f3f5ff] p-4 text-sm text-[#52667f]">O percentual de repasse é configurado uma única vez no PDV, e vale para todos os administradores vinculados. <NuxtLink :to="form.pdv_id ? `/dashboard/superadmin/pontos-de-venda/${form.pdv_id}/editar` : '/dashboard/superadmin/pontos-de-venda'" class="font-semibold text-it-primary underline">Configurar repasse no PDV</NuxtLink></p>
                    </div>
                    </template>

                    <div class="border-t border-gray-200"></div>

                    <Button type="submit" fullWidth :loading="loading" size="lg">
                        <i v-if="!loading" class="pi pi-check"></i>
                        Cadastrar Usuário
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>
