<script setup>
  import { computed, ref } from 'vue';
  import { mockDemoAccounts } from '~/mocks/api';
  import RegistrationShell from '~/components/auth/RegistrationShell.vue';
  import InputText from '~/components/forms/InputText.vue';
  import InputPassword from '~/components/forms/InputPassword.vue';
  import Button from '~/components/forms/Button.vue';
  import { useToast } from 'primevue/usetoast';
  import { getDashboardByType, getUserType } from '~/utils/authRedirect';

  const toast = useToast();

  // Configurar título da página
  useHead({
    title: 'Login - Identifica Trânsito',
    meta: [
      { name: 'description', content: 'Faça login para gerenciar seus veículos e etiquetas.' }
    ]
  });

  definePageMeta({
    middleware: ['logged']
  });

  const email = ref('');
  const password = ref('');
  const loading = ref(false);
  const errorMessage = ref('');
  const config = useRuntimeConfig();
  const isMockMode = computed(() => config.public.useMocks);

  const handleLogin = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
      const { $api } = useNuxtApp();
      const response = await $api('/login', {
        method: 'POST',
        body: {
          email: email.value,
          password: password.value
        }
      });

      const token = response?.token || response?.data?.token || response?.access_token;
      const user = response?.user || response?.data?.user || response?.data || null;

      if (!token) {
        throw new Error('Token ausente na resposta.');
      }

      const auth = useAuth();
      auth.setSession(token, user);

      const userType = getUserType(user);
      await navigateTo(getDashboardByType(userType), { external: true });
    } catch (error) {
      const apiMessage = error?.data?.message || error?.data?.error;
      errorMessage.value = apiMessage || error?.message || 'Nao foi possivel fazer login. Tente novamente.';
      toast.add({ severity: 'error', summary: 'Erro de Login', detail: errorMessage.value, life: 5000 });
    } finally {
      loading.value = false;
    }
  };

  const loginWithDemoAccount = async (account) => {
    if (loading.value) return;
    email.value = account.email;
    password.value = account.password;
    await handleLogin();
  };
</script>


<template>
  <RegistrationShell mode="login">
    <h1>Bom ter você por aqui.</h1>
    <p class="login-description">Entre na sua conta para acompanhar seus veículos, etiquetas e pedidos.</p>

    <form @submit.prevent="handleLogin" class="login-fields space-y-6">
      <p v-if="errorMessage" class="login-error" role="alert">{{ errorMessage }}</p>
      <!-- Email -->
      <InputText
        v-model="email"
        type="email"
        id="email"
        label="E-mail"
        labelClass="block text-md font-bold text-gray-600 mb-2"
        placeholder="Digite seu e-mail de login"
        required
        showIcon
        icon="pi pi-envelope"
        inputClass="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent placeholder-gray-400"
      />

      <!-- Senha -->
      <div>
        <InputPassword
          v-model="password"
          id="password"
          label="Senha"
          showIcon
          icon="pi pi-lock"
          placeholder="••••••••••••••••"
          required
          inputClass="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent placeholder-gray-400"
        />
        <div class="text-right mt-2">
          <a href="/auth/forgot-password" class="text-[12.5px] text-blue-600 hover:text-blue-700 font-medium">
            Esqueceu sua senha?
          </a>
        </div>
      </div>

      <!-- Botão Entrar -->
      <Button type="submit" fullWidth size="md" buttonClass="auth-primary-button" :loading="loading">
        <span>Entrar na minha conta</span>
        <i class="pi pi-arrow-right text-base" aria-hidden="true"></i>
      </Button>
    </form>

    <section v-if="isMockMode" class="demo-access" aria-label="Acessos de demonstração">
      <p class="demo-access__eyebrow">Acessos de demonstração</p>
      <p class="demo-access__hint">Entre em cada perfil com os dados já preenchidos.</p>
      <div class="demo-access__grid">
        <button
          v-for="account in mockDemoAccounts"
          :key="account.email"
          type="button"
          class="demo-access__button"
          :disabled="loading"
          @click="loginWithDemoAccount(account)"
        >
          <span>{{ account.label }}</span>
          <small>{{ account.description }}</small>
        </button>
      </div>
    </section>

    <!-- Cadastro -->
    <p class="text-center text-gray-600 text-base mt-6">
      Não tem uma conta? 
      <a href="/auth/register" class="text-blue-600 hover:text-blue-700 font-medium">
        Criar minha conta
      </a>
    </p>
  </RegistrationShell>
</template>


<style scoped>
h1{font-family:Manrope,Arial,sans-serif;color:#181a2b;font-size:36px;font-weight:600;line-height:1.2;letter-spacing:-1.2px;margin:0 0 14px}
.login-description{color:#646777;font-size:15px;line-height:1.75;margin:0 0 32px;max-width:390px}
.login-fields{display:grid;gap:24px}.login-fields>:not(:first-child){margin-top:0!important}
:deep(label){font-size:14px;font-weight:600;color:#181a2b;margin-bottom:9px}:deep(label .text-red-500){display:none}
:deep(.p-inputtext){width:100%;height:54px!important;border-radius:9px;border:1px solid #dcdde7;background:#fff;color:#181a2b;font-family:'DM Sans',Arial,sans-serif;font-size:15px;box-shadow:none}
:deep(input::placeholder){color:#858997}:deep(.p-inputtext:focus){border-color:#2420db!important;box-shadow:0 0 0 3px #2420db15!important}:deep(.p-inputicon){font-size:15px;color:#9196a6}
.login-fields a{display:inline-flex;color:#2420db;font-size:13px;line-height:1.5;margin-top:4px;text-decoration:none}.login-fields a:hover{text-decoration:underline}
:deep(.auth-primary-button){background:#2420db;color:#fff;border-radius:9px;height:54px!important;font-size:15px;font-weight:650;justify-content:space-between;padding-inline:22px;transition:background-color .2s}:deep(.auth-primary-button:hover:not(:disabled)){background:#1510a5}
form+p{text-align:center;color:#646777;font-size:14px;line-height:1.7;margin-top:26px}form+p a{color:#2420db;font-weight:600}
.login-error{padding:13px 16px;border:1px solid #f2c5c5;background:#fff1f1;border-radius:9px;color:#a52c2c;font-size:14px;line-height:1.5;margin:0}
a:focus-visible{outline:3px solid #2420db;outline-offset:4px;border-radius:3px}
@media(max-width:800px){h1{font-size:31px}.login-description{margin-bottom:26px}.login-fields{gap:22px}:deep(.p-inputtext){font-size:16px}}
  .demo-access {
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #e8ebf3;
  }

  .demo-access__eyebrow {
    margin: 0;
    color: #17204b;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
  }

  .demo-access__hint {
    margin: .35rem 0 .85rem;
    color: #64708a;
    font-size: .8125rem;
    line-height: 1.35;
  }

  .demo-access__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .5rem;
  }

  .demo-access__button {
    min-width: 0;
    padding: .7rem .55rem;
    border: 1px solid #dce2ef;
    border-radius: .625rem;
    background: #f8faff;
    color: #111a38;
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: border-color 160ms ease, background-color 160ms ease, transform 160ms ease;
  }

  .demo-access__button:hover:not(:disabled), .demo-access__button:focus-visible {
    border-color: #2856e9;
    background: #eef3ff;
    outline: none;
    transform: translateY(-1px);
  }

  .demo-access__button:disabled { cursor: wait; opacity: .6; }
  .demo-access__button span, .demo-access__button small { display: block; }
  .demo-access__button span { font-size: .75rem; font-weight: 700; line-height: 1.2; }
  .demo-access__button small { margin-top: .25rem; color: #64708a; font-size: .6875rem; line-height: 1.25; }

  @media (max-width: 540px) {
    .demo-access__grid { grid-template-columns: 1fr; }
  }

</style>
