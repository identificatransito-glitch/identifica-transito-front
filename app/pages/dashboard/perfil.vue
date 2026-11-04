<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
import { LogOut, UserRound, X } from '@lucide/vue';
import { useToast } from 'primevue/usetoast';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import InputText from '~/components/forms/InputText.vue';
import InputPassword from '~/components/forms/InputPassword.vue';
import PasswordStrengthIndicator from '~/components/forms/PasswordStrengthIndicator.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import Button from '~/components/forms/Button.vue';
import AvatarCropModal from '~/components/dashboard/AvatarCropModal.vue';
import {formatBirthDate} from '~/utils/date';
import { BIOLOGICAL_SEX_OPTIONS, getBiologicalSexFromUser } from '~/utils/userBiologicalSex';

definePageMeta({
    layout: 'dashboard'
});

useHead({
    title: 'Meu Perfil - Identifica Trânsito',
    meta: [
        { name: 'description', content: 'Gerencie suas informações de perfil.' }
    ]
});

// Função helper para assets com baseURL
const config = useRuntimeConfig();
const assetWithBase = (path: string) => {
    if (path.startsWith('data:') || path.startsWith('http')) {
        return path; // Já é URL absoluta ou data URL
    }
    return `${config.app.baseURL}${path}`.replace(/\/+/g, '/').replace(':/', '://');
};

const auth = useAuth();
auth.init();

// Dados do usuário
const userProfile = ref({
    nome: '',
    cpf: '',
    email: '',
    gender: '',
    telefone: '',
    dataNascimento: '',
    avatar: '/images/dashboard/avatar.jpg'
});

const isEditing = ref(false);
const isSaving = ref(false);



const toast = useToast();

const editingData = ref({ ...userProfile.value });
const selectedFile = ref<File | null>(null);
const showCropModal = ref(false);
const imageToCrop = ref('');

watchEffect(() => {
    if (isEditing.value) return;
    const user = auth.user.value;
    if (!user) return;

    userProfile.value = {
        nome: (user.name as string) || '',
        cpf: (user.cpf as string) || '',
        email: (user.email as string) || '',
        gender: getBiologicalSexFromUser(user as Record<string, unknown>),
        telefone: (user.phone as string) || '',
        dataNascimento: (user.birth_date as string) || '',
        avatar: (user.photo as string) || '/images/dashboard/avatar.jpg'
    };
    editingData.value = { ...userProfile.value };
});

const startEditing = () => {
    isEditing.value = true;
    editingData.value = { ...userProfile.value };
};

const cancelEditing = () => {
    isEditing.value = false;
    editingData.value = { ...userProfile.value };
    selectedFile.value = null;
};

const saveProfile = async () => {
    isSaving.value = true;

    try {
        const { $api } = useNuxtApp();
        const userId = auth.user.value?.id;

        if (!userId) {
            throw new Error('Usuário não encontrado');
        }

        // Preparar FormData para enviar arquivo e dados
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('name', editingData.value.nome);
        formData.append('email', editingData.value.email);
        const cpf = editingData.value.cpf.replace(/\D/g, '');
        if (cpf !== userProfile.value.cpf.replace(/\D/g, '')) formData.append('cpf', cpf);
        const genderToSend = editingData.value.gender;
        if (genderToSend) {
            formData.append('gender', genderToSend);
        }
        formData.append('phone', editingData.value.telefone.replace(/\D/g, ''));
        formData.append('birth_date', formatBirthDate(editingData.value.dataNascimento));
        
        // Adicionar foto se foi selecionada
        if (selectedFile.value) {
            formData.append('photo', selectedFile.value);
        }

        const response = await $api(`/users/${userId}`, {
            method: 'POST',
            body: formData
        }) as any;

        // Atualizar dados locais
        const updatedUser = response?.data || response;
        auth.setSession(auth.getToken() || '', { ...auth.user.value, ...updatedUser });
        
        userProfile.value = { ...editingData.value };
        isEditing.value = false;
        selectedFile.value = null;

        toast.add({
            severity: 'success',
            summary: 'Perfil atualizado!',
            detail: 'Suas informações foram salvas com sucesso.',
            life: 5000
        });
    } catch (error: any) {
        const apiMessage = error?.data?.message || error?.data?.error;
        const errorMsg = apiMessage || error?.message || 'Não foi possível atualizar o perfil.';
        
        toast.add({
            severity: 'error',
            summary: 'Erro ao atualizar perfil',
            detail: errorMsg,
            life: 5000
        });
    } finally {
        isSaving.value = false;
    }
};

const uploadAvatar = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) {
        // Validar tipo de arquivo
        if (!file.type.startsWith('image/')) {
            toast.add({
                severity: 'error',
                summary: 'Arquivo inválido',
                detail: 'Por favor, selecione uma imagem válida.',
                life: 5000
            });
            input.value = '';
            return;
        }

        // Validar tamanho (máximo 5MB)
        if (file.size > 5 * 1024 * 1024) {
            toast.add({
                severity: 'error',
                summary: 'Arquivo muito grande',
                detail: 'A imagem deve ter no máximo 5MB.',
                life: 5000
            });
            input.value = '';
            return;
        }

        // Carregar imagem para o crop
        const reader = new FileReader();
        reader.onload = (e) => {
            imageToCrop.value = e.target?.result as string;
            showCropModal.value = true;
        };
        reader.readAsDataURL(file);
        
        // Limpar input para permitir selecionar a mesma imagem novamente
        input.value = '';
    }
};

const handleCropConfirm = (blob: Blob) => {
    // Converter blob em File
    const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
    selectedFile.value = file;
    
    // Criar preview
    const reader = new FileReader();
    reader.onload = (e) => {
        editingData.value.avatar = e.target?.result as string;
    };
    reader.readAsDataURL(file);
    
    // Fechar modal
    showCropModal.value = false;
};

const handleCropCancel = () => {
    showCropModal.value = false;
    imageToCrop.value = '';
};

// Segurança
const securityData = ref({
    senhaAtual: '',
    novaSenha: '',
    confirmarSenha: ''
});

const showSecurityForm = ref(false);
const isChangingPassword = ref(false);
const showLogoutModal = ref(false);
const isLoggingOut = ref(false);

const cancelPasswordChange = () => {
    securityData.value = {
        senhaAtual: '',
        novaSenha: '',
        confirmarSenha: ''
    };
    showSecurityForm.value = false;
};

const passwordError = computed(() => {
    if (!securityData.value.novaSenha || !securityData.value.confirmarSenha) return '';
    if (securityData.value.novaSenha !== securityData.value.confirmarSenha) return 'A nova senha e a confirmação precisam ser iguais.';
    return '';
});

const changePassword = async () => {
    // Validações
    if (passwordError.value) {
        toast.add({
            severity: 'error',
            summary: 'Erro na validação',
            detail: passwordError.value,
            life: 5000
        });
        return;
    }

    if (!securityData.value.senhaAtual) {
        toast.add({
            severity: 'error',
            summary: 'Senha atual obrigatória',
            detail: 'Por favor, insira sua senha atual.',
            life: 5000
        });
        return;
    }

    if (securityData.value.novaSenha.length < 8) {
        toast.add({
            severity: 'error',
            summary: 'Senha muito curta',
            detail: 'A nova senha deve ter no mínimo 8 caracteres.',
            life: 5000
        });
        return;
    }

    isChangingPassword.value = true;

    try {
        const { $api } = useNuxtApp();
        const userId = auth.user.value?.id;

        if (!userId) {
            throw new Error('Usuário não encontrado');
        }

        await $api(`/users/${userId}/password`, {
            method: 'PUT',
            body: {
                current_password: securityData.value.senhaAtual,
                new_password: securityData.value.novaSenha
            }
        });

        // Limpar formulário e fechar
        securityData.value = {
            senhaAtual: '',
            novaSenha: '',
            confirmarSenha: ''
        };
        showSecurityForm.value = false;

        toast.add({
            severity: 'success',
            summary: 'Senha alterada!',
            detail: 'Sua senha foi atualizada com sucesso.',
            life: 5000
        });
    } catch (error: any) {
        const apiMessage = error?.data?.message || error?.data?.error;
        const errorMsg = apiMessage || error?.message || 'Não foi possível alterar a senha.';
        
        toast.add({
            severity: 'error',
            summary: 'Erro ao alterar senha',
            detail: errorMsg,
            life: 5000
        });
    } finally {
        isChangingPassword.value = false;
    }
};

const openLogoutModal = () => {
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
        const apiMessage = (result as any)?.error?.data?.message || (result as any)?.error?.data?.error;
        toast.add({
            severity: 'error',
            summary: 'Erro ao sair',
            detail: apiMessage || 'Não foi possível encerrar sua sessão. Tente novamente.',
            life: 5000
        });
        isLoggingOut.value = false;
        return;
    }

    showLogoutModal.value = false;
    isLoggingOut.value = false;
};
</script>

<template>
    <div class="space-y-6 md:space-y-7">
        <!-- Hero Section -->
        <HeroSection
            title="Meu perfil"
            subtitle="Mantenha suas informações pessoais atualizadas."
            greeting="Dados da conta"
            :greeting-icon="UserRound"
            :showButton="false"
        />

        <!-- Informações do Usuário -->
        <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eaf0ff] text-[#1f46ee]"><UserRound :size="18" :stroke-width="1.9" aria-hidden="true" /></div>
                    <div>
                        <h2 class="relative top-0.5 text-lg! font-semibold text-[#172b4d] mb-1!">Informações pessoais</h2>
                    </div>
                </div>
                <div class="hidden gap-2 md:flex">
                    <Button
                        v-if="!isEditing"
                        label="Editar Perfil"
                        icon="pi pi-pencil"
                        @click="startEditing"
                        size="sm"
                    />
                    <template v-else>
                        <Button
                            label="Cancelar"
                            icon="pi pi-times"
                            @click="cancelEditing"
                            size="sm"
                            variant="danger"
                        />
                        <Button
                            label="Salvar"
                            :icon="isSaving ? 'pi pi-spin pi-spinner' : 'pi pi-check'"
                            @click="saveProfile"
                            :disabled="isSaving"
                            size="sm"
                        />
                    </template>
                </div>
            </div>

            <!-- Avatar Section -->
            <div class="mb-7 flex flex-col items-center md:flex-row md:text-left">
                <div class="relative mb-3 md:mb-0 md:mr-3.5">
                    <img
                        :src="assetWithBase(isEditing ? editingData.avatar : userProfile.avatar)"
                        :alt="userProfile.nome"
                        class="h-20 w-20 rounded-full border-2 border-slate-200 object-cover"
                    />
                    <label
                        v-if="isEditing"
                        for="avatar-upload"
                        class="absolute bottom-0 right-0 flex h-9 w-9 items-center justify-center rounded-full bg-[#1f46ee] text-white transition-colors hover:bg-[#1739d4] cursor-pointer"
                    >
                        <i class="pi pi-camera text-lg"></i>
                    </label>
                    <input
                        id="avatar-upload"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="uploadAvatar"
                    />
                </div>
                <div class="flex flex-col items-center md:items-start">
                    <p class="relative -top-0.5 text-base font-semibold text-[#172b4d] mb-0!">{{ userProfile.nome }}</p>
                    <p class="mt-1 text-sm text-slate-600 mb-0!">{{ userProfile.email }}</p>
                    <div class="mt-4 flex gap-2 md:hidden">
                        <Button
                            v-if="!isEditing"
                            label="Editar perfil"
                            icon="pi pi-pencil"
                            @click="startEditing"
                            size="sm"
                        />
                        <template v-else>
                            <Button label="Cancelar" icon="pi pi-times" @click="cancelEditing" size="sm" variant="danger" />
                            <Button label="Salvar" :icon="isSaving ? 'pi pi-spin pi-spinner' : 'pi pi-check'" @click="saveProfile" :disabled="isSaving" size="sm" />
                        </template>
                    </div>
                </div>
            </div>

            <!-- Form Grid -->
            <div class="profile-details-fields space-y-5">
                <!-- Nome Completo + CPF na mesma linha -->
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <InputText
                        v-model="editingData.nome"
                        label="Nome Completo"
                        placeholder="Seu nome e sobrenome"
                        icon="pi pi-user"
                        :readonly="!isEditing"
                        :disabled="!isEditing"
                        wrapper-class="w-full"
                        inputClass="w-full"
                        required
                    />

                    <div class="relative">
                        <InputText
                            v-model="editingData.cpf"
                            label="CPF"
                            mask="999.999.999-99"
                            placeholder="000.000.000-00"
                            icon="pi pi-id-card"
                            :readonly="!isEditing"
                            :disabled="!isEditing"
                            wrapper-class="w-full"
                            inputClass="w-full"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <InputText
                        v-model="editingData.email"
                        label="E-mail"
                        placeholder="seu.email@example.com"
                        icon="pi pi-envelope"
                        type="email"
                        :readonly="!isEditing"
                        :disabled="!isEditing"
                        wrapper-class="w-full"
                        inputClass="w-full"
                        required
                    />

                    <div class="relative">
                        <SelectInput
                            v-model="editingData.gender"
                            id="gender"
                            label="Sexo Biológico"
                            :options="BIOLOGICAL_SEX_OPTIONS"
                            placeholder="Selecione"
                            icon="pi pi-user"
                            wrapperClass="w-full"
                            :disabled="!isEditing"
                            required
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <InputText
                        v-model="editingData.telefone"
                        label="Telefone"
                        placeholder="(11) 99999-9999"
                        icon="pi pi-phone"
                        mask="(99) 99999-9999"
                        :readonly="!isEditing"
                        :disabled="!isEditing"
                        wrapper-class="w-full"
                        inputClass="w-full"
                        required
                    />

                    <InputText
                        v-model="editingData.dataNascimento"
                        label="Data de Nascimento"
                        placeholder="DD/MM/YYYY"
                        mask="99/99/9999"
                        icon="pi pi-calendar"
                        type="text"
                        :readonly="!isEditing"
                        :disabled="!isEditing"
                        wrapper-class="w-full"
                        inputClass="w-full"
                        required
                    />
                </div>
            </div>
        </section>

        <!-- Segurança -->
        <section id="alterar-senha" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
            <div class="mb-6 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h2 class="relative top-0.5 text-xl! font-semibold text-[#172b4d] mb-1!">Segurança</h2>
                    <p class="mt-1 text-sm font-medium text-slate-600 mb-0!">Mantenha sua conta protegida com uma senha segura.</p>
                </div>
                <Button
                    v-if="!showSecurityForm"
                    label="Alterar Senha"
                    icon="pi pi-key"
                    @click="showSecurityForm = true"
                    size="sm"
                    button-class="password-action-button"
                />
            </div>

            <!-- Security Form -->
            <div v-if="showSecurityForm" class="bg-gray-50 dark:bg-surface-700 rounded-lg p-6 space-y-4">
                <InputPassword
                    v-model="securityData.senhaAtual"
                    label="Senha Atual"
                    placeholder="Digite sua senha atual"
                    id="senha-atual"
                    autocomplete="current-password"
                    wrapper-class="w-full"
                    inputClass="w-full h-[52px]"
                />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputPassword
                            v-model="securityData.novaSenha"
                            label="Nova Senha"
                            placeholder="Digite uma nova senha"
                            id="nova-senha"
                            autocomplete="new-password"
                            wrapper-class="w-full"
                            inputClass="w-full h-[52px]"
                        />
                        <PasswordStrengthIndicator :password="securityData.novaSenha" />
                    </div>

                    <div>
                        <InputPassword
                            v-model="securityData.confirmarSenha"
                            label="Confirmar Nova Senha"
                            placeholder="Confirme a nova senha"
                            id="confirmar-senha"
                            autocomplete="new-password"
                            wrapper-class="w-full"
                            inputClass="w-full h-[52px]"
                        />
                        <div v-if="securityData.confirmarSenha" class="mt-2">
                            <div class="flex items-center gap-2">
                                <svg 
                                    v-if="!passwordError && securityData.novaSenha" 
                                    class="w-5 h-5 text-green-600" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <svg 
                                    v-else-if="passwordError" 
                                    class="w-5 h-5 text-red-600" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                <p :class="!passwordError && securityData.novaSenha ? 'text-green-600' : 'text-red-600'" class="text-sm font-medium">
                                    {{ !passwordError && securityData.novaSenha ? 'As senhas são iguais' : 'As senhas não correspondem' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-start gap-2 text-sm font-medium leading-6 text-slate-600" role="note">
                    <i class="pi pi-info-circle mt-1 text-[#1f46ee]" aria-hidden="true"></i>
                    <p>
                        Sua senha deve ter no mínimo 8 caracteres, incluindo letras maiúsculas, minúsculas, números e símbolos.
                    </p>
                </div>

                <div class="flex gap-2 justify-end pt-4">
                    <Button
                        label="Cancelar"
                        icon="pi pi-times"
                        @click="cancelPasswordChange"
                        size="sm"
                        variant="danger"
                        :disabled="isChangingPassword"
                    />
                    <Button
                        label="Alterar Senha"
                        icon="pi pi-check"
                        @click="changePassword"
                        :disabled="isChangingPassword || !!passwordError || !securityData.senhaAtual || securityData.novaSenha.length < 8"
                        :loading="isChangingPassword"
                        size="sm"
                        button-class="password-action-button"
                    />
                </div>
            </div>

            <!-- Info Box -->
            <div v-else class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-[#fafafa] p-4">
                <i class="pi pi-shield mt-0.5 text-[#1f46ee]" aria-hidden="true"></i>
                <p class="text-sm font-medium leading-6 text-slate-600 mb-0!">
                    Altere a senha periodicamente para maior segurança. Nunca utilize senhas fracas.
                </p>
            </div>
        </section>

        <!-- Encerramento da conta -->
        <section class="logout-card rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl! font-semibold text-[#172b4d] mb-1!">Sair da conta</h2>
                    <p class="max-w-2xl text-sm font-medium leading-6 text-slate-600 mb-0!">
                        Encerre sua sessão com segurança neste dispositivo. Você poderá entrar novamente quando quiser.
                    </p>
                </div>
                <button type="button" class="logout-card-button inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-semibold transition-colors sm:w-auto" @click="openLogoutModal">
                    <LogOut :size="18" :stroke-width="1.9" aria-hidden="true" />
                    <span>Sair da conta</span>
                </button>
            </div>
        </section>

        <!-- Modal de Crop de Avatar -->
        <AvatarCropModal
            :show="showCropModal"
            :imageUrl="imageToCrop"
            @close="handleCropCancel"
            @confirm="handleCropConfirm"
        />

        <!-- Confirmação de logout -->
        <Teleport to="body">
            <Transition name="logout-modal-fade">
                <div v-if="showLogoutModal" class="logout-modal-backdrop" role="presentation" @click.self="closeLogoutModal">
                    <section class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
                        <button type="button" class="logout-modal-close" aria-label="Fechar confirmação" :disabled="isLoggingOut" @click="closeLogoutModal">
                            <X :size="20" :stroke-width="1.9" />
                        </button>
                        <div class="logout-modal-mark" aria-hidden="true"><LogOut :size="24" :stroke-width="1.9" /></div>
                        <h2 id="logout-modal-title">Deseja sair da sua conta?</h2>
                        <p>Sua sessão será encerrada neste dispositivo. Será necessário fazer login novamente para acessar o painel.</p>
                        <div class="logout-modal-actions">
                            <button type="button" class="logout-modal-cancel" :disabled="isLoggingOut" @click="closeLogoutModal">Cancelar</button>
                            <button type="button" class="logout-modal-confirm" :disabled="isLoggingOut" @click="confirmLogout">
                                <i v-if="isLoggingOut" class="pi pi-spin pi-spinner" aria-hidden="true"></i>
                                <LogOut v-else :size="17" :stroke-width="1.9" aria-hidden="true" />
                                <span>{{ isLoggingOut ? 'Saindo...' : 'Sim, sair' }}</span>
                            </button>
                        </div>
                    </section>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
:deep(.password-action-button) {
    background: #1f46ee !important;
}

:deep(.password-action-button:hover) {
    background: #1739d4 !important;
}

:deep(.password-action-button:focus-visible) {
    outline: 2px solid #1f46ee;
    outline-offset: 2px;
}

.logout-card {
    background: linear-gradient(135deg, #ffffff 0%, #fcfdff 100%);
}

.logout-card-button {
    color: #c93662;
    background: #fff5f7;
    border: 1px solid #ffd6e0;
}

.logout-card-button:hover,
.logout-card-button:focus-visible {
    color: #a9254d;
    background: #ffe8ee;
    border-color: #ffb9ca;
}

.logout-card-button:focus-visible,
.logout-modal button:focus-visible {
    outline: 2px solid #1f46ee;
    outline-offset: 2px;
}

.logout-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.48);
    backdrop-filter: blur(3px);
}

.logout-modal {
    position: relative;
    width: min(100%, 440px);
    padding: 2rem;
    border: 1px solid #e2e8f0;
    border-radius: 1.25rem;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.2);
    text-align: center;
}

.logout-modal-close {
    position: absolute;
    top: 0.8rem;
    right: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    color: #64748b;
    border-radius: 0.65rem;
}

.logout-modal-close:hover { color: #172b4d; background: #f1f5f9; }
.logout-modal-mark { display: inline-flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; margin-bottom: 1rem; color: #c93662; background: #fff1f4; border: 1px solid #ffd6e0; border-radius: 1rem; }
.logout-modal h2 { margin: 0 0 0.55rem; color: #172b4d; font-size: 1.22rem; font-weight: 700; }
.logout-modal p { margin: 0 auto; max-width: 340px; color: #64748b; font-size: 0.92rem; line-height: 1.6; }
.logout-modal-actions { display: flex; gap: 0.75rem; justify-content: center; margin-top: 1.6rem; }
.logout-modal-actions button { min-height: 2.75rem; padding: 0.65rem 1.15rem; border-radius: 0.65rem; font-size: 0.9rem; font-weight: 650; transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease; }
.logout-modal-cancel { color: #475569; background: #fff; border: 1px solid #cbd5e1; }
.logout-modal-cancel:hover { background: #f8fafc; border-color: #94a3b8; }
.logout-modal-confirm { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; color: #fff; background: #c93662; border: 1px solid #c93662; }
.logout-modal-confirm:hover { background: #a9254d; border-color: #a9254d; }
.logout-modal-confirm:disabled, .logout-modal-cancel:disabled, .logout-modal-close:disabled { cursor: not-allowed; opacity: 0.6; }

.logout-modal-fade-enter-active, .logout-modal-fade-leave-active { transition: opacity 180ms ease; }
.logout-modal-fade-enter-active .logout-modal, .logout-modal-fade-leave-active .logout-modal { transition: transform 180ms ease, opacity 180ms ease; }
.logout-modal-fade-enter-from, .logout-modal-fade-leave-to { opacity: 0; }
.logout-modal-fade-enter-from .logout-modal, .logout-modal-fade-leave-to .logout-modal { opacity: 0; transform: translateY(10px) scale(0.98); }

@media (max-width: 639px) {
    .logout-modal { padding: 1.65rem 1.25rem 1.35rem; }
    .logout-modal-actions { flex-direction: column-reverse; }
    .logout-modal-actions button { width: 100%; }
    .logout-card-button { min-height: 52px; }
}

@media (max-width: 639px) {
    .profile-details-fields :deep(input),
    .profile-details-fields :deep(.p-select) {
        height: 52px !important;
        min-height: 52px !important;
    }
}
</style>
