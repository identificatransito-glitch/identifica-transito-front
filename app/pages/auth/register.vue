<script setup>
  import { computed, ref } from 'vue';
  import RegistrationShell from '~/components/auth/RegistrationShell.vue';
  import InputText from '~/components/forms/InputText.vue';
  import InputPassword from '~/components/forms/InputPassword.vue';
  import SelectInput from '~/components/forms/SelectInput.vue';
  import PasswordStrengthIndicator from '~/components/forms/PasswordStrengthIndicator.vue';
  import Button from '~/components/forms/Button.vue';
  import { formatBirthDate } from '~/utils/date';
  import { BIOLOGICAL_SEX_OPTIONS } from '~/utils/userBiologicalSex';

  // Configurar título da página
  useHead({
    title: 'Criar Conta - Identifica Trânsito',
    meta: [
      { name: 'description', content: 'Crie sua conta para gerenciar seus veículos e etiquetas.' }
    ]
  });

  definePageMeta({
    middleware: ['logged']
  });

  const formData = ref({
    fullName: '',
    email: '',
    biologicalSex: '',
    cpf: '',
    birthDate: '',
    phone: '',
    password: '',
    acceptTerms: false
  });

  const loading = ref(false);
  const errorMessage = ref('');
  const successMessage = ref('');
  const formRef = ref(null);

  const toast = useToast();
  const handleRegister = async () => {
    const formEl = formRef.value;
    if (formEl && !formEl.reportValidity()) {
      return;
    }

    if ((formData.value.password || '').length < 8) {
      toast.add({
        severity: 'error',
        summary: 'Erro ao criar conta',
        detail: 'A senha deve ter no minimo 8 caracteres.',
        life: 5000
      });
      return;
    }

    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
      const { $api } = useNuxtApp();
      await $api('/users', {
        method: 'POST',
        body: {
          name: formData.value.fullName,
          email: formData.value.email,
          gender: formData.value.biologicalSex || null,
          cpf: formData.value.cpf.replace(/\D/g, ''),
          birth_date: formatBirthDate(formData.value.birthDate),
          phone: formData.value.phone.replace(/\D/g, ''),
          password: formData.value.password
        }
      });

      successMessage.value = 'Conta criada com sucesso. Voce pode entrar agora.';
      toast.add({ severity: 'success', summary: 'Sucesso', detail: successMessage.value, life: 5000 });
      await navigateTo('/auth/login', { external: true });
    } catch (error) {
      if (error?.data?.errors) {
        const errorsArray = Object.values(error.data.errors).flat();
        errorMessage.value = errorsArray.join('\n');
      } else {
        const apiMessage = error?.data?.message || error?.data?.error;
        errorMessage.value = apiMessage || error?.message || 'Nao foi possivel criar sua conta.';
      }
      toast.add({ severity: 'error', summary: 'Erro ao criar conta', detail: errorMessage.value, life: 5000 });
    } finally {
      loading.value = false;
    }
  };
</script>

<template>
  <RegistrationShell>
    <h1 class="w-full font-bold text-center text-it-primary mb-2">Crie sua conta</h1>
    <p class="register-description w-full text-it-gray text-md text-center mb-8">Preencha seus dados para começar a fazer parte do Identifica Trânsito.</p>

    <form ref="formRef" @submit.prevent="handleRegister" class="registration-fields space-y-4">
      <InputText
        v-model="formData.fullName"
        type="text"
        id="fullName"
        label="Nome Completo"
        placeholder="Digite seu nome completo"
        required
        showIcon
        icon="pi pi-user"
        inputClass="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent placeholder-gray-400"
      />

      <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
        <InputText
          v-model="formData.email"
          type="email"
          id="email"
          label="E-mail"
          placeholder="seu@email.com"
          required
          showIcon
          icon="pi pi-envelope"
          inputClass="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent placeholder-gray-400"
          wrapperClass="md:col-span-3"
        />

        <SelectInput
          v-model="formData.biologicalSex"
          id="biologicalSex"
          label="Sexo Biológico"
          :options="BIOLOGICAL_SEX_OPTIONS"
          placeholder="Selecione"
          icon="pi pi-user"
          required
          wrapperClass="md:col-span-2"
        />
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <InputText
          v-model="formData.cpf"
          type="text"
          id="cpf"
          label="CPF"
          placeholder="000.000.000-00"
          mask="999.999.999-*?*"
          required
          showIcon
          icon="pi pi-id-card"
          inputClass="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent placeholder-gray-400"
        />

        <InputText
          v-model="formData.birthDate"
          type="text"
          id="birthDate"
          label="Data de Nascimento"
          placeholder="01/10/1991"
          mask="99/99/9999"
          required
          showIcon
          icon="pi pi-calendar"
          inputClass="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent placeholder-gray-400"
        />

        <InputText
          v-model="formData.phone"
          type="tel"
          id="phone"
          label="Celular / Whatsapp"
          placeholder="99999-9999"
          mask="(99) ?99999-9999"
          required
          showIcon
          icon="pi pi-phone"
          inputClass="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent placeholder-gray-400"
        />

        <InputPassword
          v-model="formData.password"
          id="password"
          label="Senha"
          placeholder="••••••••••••••••"
          required
          showIcon
          icon="pi pi-lock"
            :minlength="8"
          inputClass="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent placeholder-gray-400"
        >
          <PasswordStrengthIndicator :password="formData.password" />
        </InputPassword>
      </div>

      <!-- Aceite de Termos -->
      <div class="registration-consent flex items-start gap-3 pt-2 mb-6">
        <input
          type="checkbox"
          v-model="formData.acceptTerms"
          id="acceptTerms"
          required
          class="mt-1 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
        />
        <label for="acceptTerms" class="text-sm text-gray-600">
          Declaro que li e estou de acordo com os termos da 
          <NuxtLink to="/politica-de-privacidade#base-legal" target="_blank" class="text-blue-600 hover:text-blue-700 font-medium">Lei Geral de Proteção de Dados (LGPD)</NuxtLink> 
          e com a 
          <NuxtLink to="/politica-de-privacidade" target="_blank" class="text-blue-600 hover:text-blue-700 font-medium">Política de Privacidade</NuxtLink>.
        </label>
      </div>

      <!-- Botão Cadastrar -->
      <Button type="submit" fullWidth :loading="loading" size="md" buttonClass="auth-primary-button">
        <span>Cadastrar</span>
        <i class="pi pi-arrow-right text-base" aria-hidden="true"></i>
      </Button>
    </form>

    <!-- Já tem conta -->
    <p class="text-center text-gray-600 text-sm mt-6">
      Já tem uma conta? 
      <NuxtLink to="/auth/login" class="text-blue-600 hover:text-blue-700 font-medium">
        Fazer Login
      </NuxtLink>
    </p>
  </RegistrationShell>
</template>

<style scoped>
h1{font-family:Manrope,Arial,sans-serif;color:#181a2b!important;text-align:left!important;font-size:35px;font-weight:600;line-height:1.2;letter-spacing:-1.2px;margin:0 0 12px}
.register-description{color:#646777;text-align:left;font-size:14px;line-height:1.7;margin-bottom:28px}
.registration-fields{display:grid;gap:18px}.registration-fields>:not(:first-child){margin-top:0!important}.registration-fields>.grid{gap:18px 16px}
:deep(label){font-size:13px;font-weight:600;color:#181a2b;margin-bottom:7px}:deep(label .text-red-500){display:none}
:deep(.p-inputtext),:deep(.p-inputmask),:deep(.p-select){width:100%;height:50px!important;min-height:50px;border-radius:9px;border:1px solid #dcdde7;background:#fff;color:#181a2b;font-family:'DM Sans',Arial,sans-serif;font-size:14px;box-shadow:none}
:deep(input::placeholder),:deep(.p-select-label.p-placeholder){color:#858997}:deep(.p-inputtext:focus),:deep(.p-inputmask:focus),:deep(.p-select.p-focus){border-color:#2420db!important;box-shadow:0 0 0 3px #2420db15!important}
:deep(.p-select-label){padding-block:0;display:flex;align-items:center}:deep(.p-inputicon){font-size:15px;color:#9196a6}
.registration-consent{padding-top:4px;margin-bottom:0;gap:10px}.registration-consent input{accent-color:#2420db;flex-shrink:0}.registration-consent label{font-size:12px;line-height:1.75;font-weight:400;margin-bottom:0;color:#646777}.registration-consent a{color:#2420db}
:deep(.auth-primary-button){background:#2420db;color:#fff;border-radius:9px;height:54px!important;font-size:14px;font-weight:650;justify-content:space-between;padding-inline:22px;transition:background-color .2s}:deep(.auth-primary-button:hover:not(:disabled)){background:#1510a5}
form+p{text-align:center;color:#646777;font-size:13px;line-height:1.7;margin-top:22px}form+p a{color:#2420db}
@media(max-width:800px){h1{font-size:30px}.register-description{margin-bottom:24px}}
@media(max-width:639px){.registration-fields>.grid{grid-template-columns:1fr;gap:17px}:deep(.p-inputtext),:deep(.p-inputmask),:deep(.p-select){font-size:16px}}
</style>
