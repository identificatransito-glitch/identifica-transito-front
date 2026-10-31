<script setup>
  import { ref, computed } from 'vue';
  import { useRoute } from 'vue-router';
  import RegistrationShell from '~/components/auth/RegistrationShell.vue';
  import { ArrowLeft, ArrowRight, KeyRound, ShieldCheck, CircleAlert, Check, X } from '@lucide/vue';
  import InputPassword from '~/components/forms/InputPassword.vue';
  import PasswordStrengthIndicator from '~/components/forms/PasswordStrengthIndicator.vue';
  import Button from '~/components/forms/Button.vue';
  import { useToast } from 'primevue/usetoast';

  const toast = useToast();
  const route = useRoute();

  definePageMeta({
    middleware: ['logged']
  });

  // Configurar título da página
  useHead({
    title: 'Redefinir Senha - Identifica Trânsito',
    meta: [
      { name: 'description', content: 'Redefinir sua senha de acesso.' }
    ]
  });

  // Extrair token e email da URL
  // Trata caso onde o backend gera URL malformada com parâmetros duplicados
  const token = computed(() => {
    const t = route.query.token;
    return Array.isArray(t) ? t[0] : (t || '');
  });
  const email = computed(() => {
    const e = route.query.email;
    const raw = Array.isArray(e) ? e[e.length - 1] : (e || '');
    return decodeURIComponent(raw).split('?')[0];
  });

  const password = ref('');
  const confirmPassword = ref('');
  const loading = ref(false);
  const invalidToken = ref(!token.value || !email.value);

  // Validações
  const passwordsMatch = computed(() => password.value === confirmPassword.value && password.value.length > 0);
  const passwordValid = computed(() => password.value.length >= 8);
  const confirmPasswordValid = computed(() => confirmPassword.value.length >= 8);
  const isFormValid = computed(() => passwordsMatch.value && passwordValid.value && confirmPasswordValid.value);

  const handleResetPassword = async () => {
    if (!isFormValid.value) {
      toast.add({
        severity: 'error',
        summary: 'Erro na validação',
        detail: 'As senhas devem ter no mínimo 8 caracteres e ser iguais.',
        life: 5000
      });
      return;
    }

    loading.value = true;

    try {
      const { $api } = useNuxtApp();
      await $api('/reset-password', {
        method: 'POST',
        body: {
          token: token.value,
          email: email.value,
          password: password.value,
          password_confirmation: confirmPassword.value
        }
      });

      toast.add({ 
        severity: 'success', 
        summary: 'Sucesso', 
        detail: 'Senha redefinida com sucesso! Redirecionando para o login...', 
        life: 5000 
      });

      setTimeout(() => {
        navigateTo('/auth/login');
      }, 2000);
    } catch (error) {
      const apiMessage = error?.data?.message || error?.data?.error;
      const errorMsg = apiMessage || error?.message || 'Não foi possível redefinir a senha.';
      
      toast.add({ 
        severity: 'error', 
        summary: 'Erro ao redefinir senha', 
        detail: errorMsg, 
        life: 5000 
      });
    } finally {
      loading.value = false;
    }
  };
</script>

<template>
  <RegistrationShell mode="recovery" class="reset-shell">
    <div v-if="invalidToken" class="reset-content" role="alert">
      <span class="reset-icon reset-warning"><CircleAlert :size="28" :stroke-width="1.6" aria-hidden="true" /></span>
      <h1>Vamos tentar de novo?</h1>
      <p class="reset-description">Este link é inválido ou expirou. Solicite um novo e-mail para redefinir sua senha com segurança.</p>
      <a href="/auth/forgot-password" class="reset-primary reset-request">Solicitar novo link <ArrowRight :size="19" aria-hidden="true" /></a>
      <p class="reset-bottom"><a href="/auth/login"><ArrowLeft :size="17" aria-hidden="true" /> Voltar para o login</a></p>
    </div>
    <div v-else class="reset-content">
      <span class="reset-icon"><KeyRound :size="27" :stroke-width="1.6" aria-hidden="true" /></span>
      <h1>Um novo começo.<br />Uma nova senha.</h1>
      <p class="reset-description">Crie sua nova senha para voltar à sua conta e seguir com a gente.</p>
      <form @submit.prevent="handleResetPassword" :aria-busy="loading" class="reset-form">
        <div>
          <InputPassword v-model="password" id="password" label="Nova senha" showIcon icon="pi pi-lock" placeholder="Digite sua nova senha" required :minlength="8" inputClass="w-full" />
          <PasswordStrengthIndicator :password="password" />
        </div>
        <div>
          <InputPassword v-model="confirmPassword" id="confirmPassword" label="Confirmar nova senha" showIcon icon="pi pi-lock" placeholder="Repita a nova senha" required :minlength="8" inputClass="w-full" />
          <p v-if="confirmPassword" class="reset-match" :class="passwordsMatch ? 'reset-match-ok' : 'reset-match-error'" role="status" aria-live="polite"><Check v-if="passwordsMatch" :size="16" aria-hidden="true" /><X v-else :size="16" aria-hidden="true" />{{ passwordsMatch ? 'As senhas são iguais' : 'As senhas não correspondem' }}</p>
        </div>
        <Button type="submit" fullWidth size="md" buttonClass="reset-primary" :loading="loading" :disabled="!isFormValid"><span>{{ loading ? 'Salvando nova senha…' : 'Salvar nova senha' }}</span><ArrowRight v-if="!loading" :size="19" aria-hidden="true" /></Button>
      </form>
      <div class="reset-note"><ShieldCheck :size="20" aria-hidden="true" /><p>Use pelo menos 8 caracteres. Combine letras, números e símbolos para uma senha mais forte.</p></div>
      <p class="reset-bottom"><a href="/auth/login"><ArrowLeft :size="17" aria-hidden="true" /> Voltar para o login</a></p>
    </div>
  </RegistrationShell>
</template>

<style scoped>
.reset-shell :deep(.registration-layout){align-items:start}.reset-shell :deep(.registration-story){min-height:690px}.reset-content{color:#181a2b}.reset-icon{display:grid;place-items:center;width:60px;height:60px;border-radius:18px;background:#eeedff;color:#2420db;margin-bottom:24px}.reset-warning{background:#fff3e1;color:#a76b13}h1{font-family:Manrope,Arial,sans-serif;font-size:34px;font-weight:600;line-height:1.23;letter-spacing:-1.2px;margin:0 0 16px;color:#181a2b}.reset-description{font-size:15px;line-height:1.75;color:#646777;margin:0 0 30px;max-width:410px}.reset-form{display:grid;gap:22px}:deep(label){font-size:14px;font-weight:600;color:#181a2b;margin-bottom:9px}:deep(label .text-red-500){display:none}:deep(.p-inputtext){width:100%;height:54px!important;border-radius:9px;border:1px solid #dcdde7;background:#fff;color:#181a2b;font-family:'DM Sans',Arial,sans-serif;font-size:15px;box-shadow:none}:deep(.p-inputtext:focus){border-color:#2420db!important;box-shadow:0 0 0 3px #2420db15!important}:deep(input::placeholder){color:#858997}:deep(.p-inputicon){font-size:16px;color:#9196a6}.reset-primary,:deep(.reset-primary){background:#2420db;color:#fff;border-radius:9px;min-height:54px;height:auto!important;font-size:14px;font-weight:600;justify-content:space-between;padding:14px 20px;gap:14px;transition:background-color .2s}.reset-primary:hover,:deep(.reset-primary:hover:not(:disabled)){background:#1510a5}:deep(.reset-primary:disabled){background:#d9d8ec;color:#74718c;opacity:1;cursor:not-allowed}:deep(.reset-primary svg){flex-shrink:0}.reset-request{display:flex;align-items:center;text-decoration:none}.reset-note{display:flex;align-items:flex-start;gap:11px;padding:16px;border-radius:11px;border:1px solid #e6e6f0;background:#f0f0f9;color:#6c7083;margin:24px 0}.reset-note svg{flex-shrink:0;color:#7772bb;margin-top:1px}.reset-note p{font-size:13px;line-height:1.65;margin:0}.reset-bottom{text-align:center;margin:26px 0 0}.reset-bottom a{display:inline-flex;align-items:center;gap:8px;color:#2420db;font-size:14px;font-weight:500;text-decoration:none}.reset-bottom a:hover{text-decoration:underline}.reset-match{display:flex;align-items:center;gap:7px;font-size:13px;margin:10px 0 0;line-height:1.5}.reset-match-ok{color:#178158}.reset-match-error{color:#b13434}a:focus-visible{outline:3px solid #746eff;outline-offset:4px;border-radius:3px}@media(max-width:800px){.reset-shell :deep(.registration-story){min-height:230px}h1{font-size:29px}.reset-icon{width:52px;height:52px;border-radius:15px;margin-bottom:20px}.reset-description{margin-bottom:26px}:deep(.p-inputtext){font-size:16px}.reset-note{padding:14px}}
</style>
