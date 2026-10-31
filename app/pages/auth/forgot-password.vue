<script setup>
  import { ref } from 'vue';
  import RegistrationShell from '~/components/auth/RegistrationShell.vue';
  import { ArrowLeft, ArrowRight, MailCheck, KeyRound, ShieldCheck } from '@lucide/vue';
  import InputText from '~/components/forms/InputText.vue';
  import Button from '~/components/forms/Button.vue';
  import { useToast } from 'primevue/usetoast';

  const toast = useToast();

  definePageMeta({
    middleware: ['logged']
  });
  
  // Configurar título da página
  useHead({
    title: 'Esqueceu sua senha - Identifica Trânsito',
    meta: [
      { name: 'description', content: 'Recupere o acesso à sua conta.' }
    ]
  });

  const email = ref('');
  const loading = ref(false);
  const emailSent = ref(false);
  const errorMessage = ref('');

  const handleResetPassword = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
      const { $api } = useNuxtApp();
      await $api('/forgot-password', {
        method: 'POST',
        body: {
          email: email.value
        }
      });
      emailSent.value = true;
      toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Email de recuperação enviado com sucesso!', life: 5000 });
    } catch (error) {
      const apiMessage = error?.data?.message || error?.data?.error;
      errorMessage.value = apiMessage || error?.message || 'Nao foi possivel enviar o email.';
      toast.add({ severity: 'error', summary: 'Erro ao recuperar senha', detail: errorMessage.value, life: 5000 });
    } finally {
      loading.value = false;
    }
  };
</script>

<template>
  <RegistrationShell mode="recovery">
    <div v-if="emailSent" class="recovery-content" role="status" aria-live="polite">
      <span class="recovery-icon recovery-success"><MailCheck :size="28" :stroke-width="1.6" aria-hidden="true" /></span>
      <h1>Confira seu e-mail.</h1>
      <p class="recovery-description">Enviamos um link de recuperação para <strong>{{ email }}</strong>. Abra a mensagem e siga as instruções para criar uma nova senha.</p>
      <div class="recovery-note"><ShieldCheck :size="20" aria-hidden="true" /><p>Não encontrou? Confira também a pasta de spam ou lixo eletrônico.</p></div>
      <Button fullWidth size="md" buttonClass="recovery-primary" @click="emailSent = false"><span>Enviar novamente</span><ArrowRight :size="19" aria-hidden="true" /></Button>
      <p class="recovery-bottom"><a href="/auth/login"><ArrowLeft :size="17" aria-hidden="true" /> Voltar para o login</a></p>
    </div>
    <div v-else class="recovery-content">
      <span class="recovery-icon"><KeyRound :size="27" :stroke-width="1.6" aria-hidden="true" /></span>
      <h1>Esqueceu sua senha?</h1>
      <p class="recovery-description">Acontece. Informe o e-mail da sua conta e enviaremos um link para você criar uma nova senha.</p>
      <form @submit.prevent="handleResetPassword" :aria-busy="loading" class="recovery-form">
        <p v-if="errorMessage" class="recovery-error" role="alert">{{ errorMessage }}</p>
        <InputText v-model="email" type="email" id="email" label="E-mail cadastrado" placeholder="seu@email.com" required showIcon icon="pi pi-envelope" inputClass="w-full" />
        <Button type="submit" fullWidth size="md" buttonClass="recovery-primary" :loading="loading"><span>{{ loading ? 'Enviando link…' : 'Enviar link de recuperação' }}</span><ArrowRight v-if="!loading" :size="19" aria-hidden="true" /></Button>
      </form>
      <div class="recovery-note"><ShieldCheck :size="20" aria-hidden="true" /><p>Use o e-mail informado no cadastro para recuperar o acesso à sua conta.</p></div>
      <p class="recovery-bottom"><a href="/auth/login"><ArrowLeft :size="17" aria-hidden="true" /> Voltar para o login</a></p>
    </div>
  </RegistrationShell>
</template>

<style scoped>
.recovery-content{color:#181a2b}.recovery-icon{display:grid;place-items:center;width:60px;height:60px;border-radius:18px;background:#eeedff;color:#2420db;margin-bottom:24px}.recovery-success{background:#e9f6ef;color:#178158}h1{font-family:Manrope,Arial,sans-serif;font-size:34px;font-weight:600;line-height:1.23;letter-spacing:-1.2px;margin:0 0 16px;color:#181a2b}.recovery-description{font-size:15px;line-height:1.75;color:#646777;margin:0 0 30px}.recovery-description strong{color:#181a2b;font-weight:600;overflow-wrap:anywhere}.recovery-form{display:grid;gap:24px}:deep(label){font-size:14px;font-weight:600;color:#181a2b;margin-bottom:9px}:deep(label .text-red-500){display:none}:deep(.p-inputtext){width:100%;height:54px!important;border-radius:9px;border:1px solid #dcdde7;background:#fff;color:#181a2b;font-family:'DM Sans',Arial,sans-serif;font-size:15px;box-shadow:none}:deep(.p-inputtext:focus){border-color:#2420db!important;box-shadow:0 0 0 3px #2420db15!important}:deep(input::placeholder){color:#858997}:deep(.p-inputicon){font-size:16px;color:#9196a6}:deep(.recovery-primary){background:#2420db;color:#fff;border-radius:9px;min-height:54px;height:auto!important;font-size:14px;font-weight:600;justify-content:space-between;padding:14px 20px;gap:14px}:deep(.recovery-primary:hover:not(:disabled)){background:#1510a5}:deep(.recovery-primary svg){flex-shrink:0}.recovery-note{display:flex;align-items:flex-start;gap:11px;padding:16px;border-radius:11px;border:1px solid #e6e6f0;background:#f0f0f9;color:#6c7083;margin:24px 0}.recovery-note svg{flex-shrink:0;color:#7772bb;margin-top:1px}.recovery-note p{font-size:13px;line-height:1.65;margin:0}.recovery-bottom{text-align:center;margin:26px 0 0}.recovery-bottom a{display:inline-flex;align-items:center;gap:8px;color:#2420db;font-size:14px;font-weight:500;text-decoration:none}.recovery-bottom a:hover{text-decoration:underline}.recovery-error{margin:0;padding:13px 16px;border:1px solid #f2c5c5;border-radius:9px;background:#fff1f1;color:#a52c2c;font-size:14px;line-height:1.6}a:focus-visible{outline:3px solid #746eff;outline-offset:4px;border-radius:3px}@media(max-width:800px){h1{font-size:29px}.recovery-icon{width:52px;height:52px;border-radius:15px;margin-bottom:20px}.recovery-description{margin-bottom:26px}:deep(.p-inputtext){font-size:16px}.recovery-note{padding:14px}}
</style>
