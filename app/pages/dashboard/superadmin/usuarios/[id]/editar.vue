<script setup lang="ts">
import HeroSection from '~/components/dashboard/HeroSection.vue';
import AdminEditUserPage from '~/pages/dashboard/admin/usuarios/[id]/editar.vue';
import FormInputText from '~/components/forms/InputText.vue';
import InputPassword from '~/components/forms/InputPassword.vue';
import PasswordStrengthIndicator from '~/components/forms/PasswordStrengthIndicator.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import Button from '~/components/forms/Button.vue';
import { useToast } from 'primevue/usetoast';
import { BIOLOGICAL_SEX_OPTIONS, getBiologicalSexFromUser } from '~/utils/userBiologicalSex';

definePageMeta({ layout: 'dashboard' });
useHead({ title: 'Editar Usuário - SuperAdmin | Identifica Trânsito' });

const { $api } = useNuxtApp();
const toast = useToast();
const route = useRoute();
const userId = Number(route.params.id);

const loading = ref(false);
const loadingUser = ref(false);

const form = ref({
    name: '',
    email: '',
    password: '',
    cpf: '',
    birth_date: '',
    phone: '',
    gender: '',
    type: 3,
});




// Converte DD/MM/YYYY → YYYY-MM-DD (formato do input type="date")
const parseBirthDate = (date: string | null | undefined): string => {
    if (!date) return '';
    const match = date.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (match) return `${match[3]}-${match[2]}-${match[1]}`;
    return date; // já está em outro formato, retorna como está
};

const fetchUser = async () => {
    loadingUser.value = true;
    try {
        const res = await $api(`/admin/users/${userId}`) as any;
        const user = res?.data ?? res;
        form.value.name = user.name ?? '';
        form.value.email = user.email ?? '';
        form.value.cpf = user.cpf ?? '';
        form.value.birth_date = parseBirthDate(user.birth_date);
        form.value.phone = user.phone ?? '';
        form.value.gender = getBiologicalSexFromUser(user);
        if (Number(user.type) === 2 && user.pdv_id) { await navigateTo(`/dashboard/superadmin/pontos-de-venda/${user.pdv_id}/editar`); return; }
        form.value.type = 3;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível carregar os dados do usuário.', life: 5000 });
        navigateTo('/dashboard/superadmin/usuarios');
    } finally {
        loadingUser.value = false;
    }
};

onMounted(() => {
    fetchUser();
});


const handleSubmit = async () => {
    if (!form.value.name || !form.value.email || !form.value.type) {
        toast.add({ severity: 'error', summary: 'Campos obrigatórios', detail: 'Preencha nome e e-mail.', life: 3000 });
        return;
    }


    loading.value = true;
    try {
        const payload: Record<string, any> = {
            name: form.value.name,
            email: form.value.email,
            type: form.value.type,
            cpf: form.value.cpf || null,
            birth_date: form.value.birth_date || null,
            phone: form.value.phone || null,
            gender: form.value.gender || null,
        };


        if (form.value.password) {
            payload.password = form.value.password;
        }

        await $api(`/admin/users/${userId}`, { method: 'PUT', body: payload });
        toast.add({ severity: 'success', summary: 'Usuário atualizado!', detail: 'Os dados foram salvos com sucesso.', life: 3000 });
        navigateTo('/dashboard/superadmin/usuarios');
    } catch (error: any) {
        const msg = error?.data?.message || error?.data?.error || 'Não foi possível salvar as alterações.';
        toast.add({ severity: 'error', summary: 'Erro ao salvar', detail: msg, life: 5000 });
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AdminEditUserPage />
    <div v-if="false" class="admin-page space-y-6 md:space-y-7">
        <HeroSection
            title="Editar Usuário"
            subtitle="Revise os dados, o perfil e as permissões deste usuário."
            greeting="Gestão de acesso"
            :showButton="true"
            buttonLabel="Voltar para Usuários"
            buttonLink="/dashboard/superadmin/usuarios"
            buttonIcon="pi pi-arrow-left"
        />

        <div class="max-w-3xl mx-auto">
            <!-- Skeleton -->
            <div v-if="loadingUser" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 space-y-6">
                <Skeleton width="40%" height="2rem" class="mb-4" />
                <div class="grid grid-cols-2 gap-6">
                    <Skeleton height="60px" />
                    <Skeleton height="60px" />
                    <Skeleton height="60px" />
                    <Skeleton height="60px" />
                    <Skeleton height="60px" />
                    <Skeleton height="60px" />
                </div>
            </div>

            <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                <div class="mb-8">
                    <h1 class="text-3xl! font-bold text-it-primary mb-2">Editar Usuário</h1>
                    <p class="text-gray-600">Atualize os campos desejados. A senha só será alterada se preenchida.</p>
                </div>

                <p class="mb-6 rounded-xl bg-[#f3f5ff] p-4 text-sm text-[#52667f]">Esta área é para clientes. Os acessos de administrador são criados e gerenciados em <NuxtLink to="/dashboard/superadmin/pontos-de-venda" class="font-semibold text-it-primary underline">Pontos de Venda</NuxtLink>.</p>
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
                                    label="Nova Senha"
                                    placeholder="Deixe em branco para manter"
                                    autocomplete="new-password"
                                    wrapper-class="w-full"
                                    inputClass="w-full"
                                    :disabled="loading"
                                />
                                <PasswordStrengthIndicator v-if="form.password" :password="form.password" />
                            </div>
                        </div>
                    </div>


                    <div class="border-t border-gray-200"></div>

                    <Button type="submit" fullWidth :loading="loading" size="lg">
                        <i v-if="!loading" class="pi pi-check"></i>
                        Salvar Alterações
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>
