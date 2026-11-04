<script setup lang="ts">
import FormInputText from '~/components/forms/InputText.vue';
import InputPassword from '~/components/forms/InputPassword.vue';
import PasswordStrengthIndicator from '~/components/forms/PasswordStrengthIndicator.vue';
const email=defineModel<string>('email',{default:''});
const password=defineModel<string>('password',{default:''});
const confirmation=defineModel<string>('confirmation',{default:''});
defineProps<{ configured?: boolean; editing?: boolean; disabled?: boolean }>();
</script>
<template>
    <section class="space-y-5 rounded-2xl border border-[#dce2ff] bg-[#f7f8ff] p-5 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="flex items-center gap-3 text-lg! font-semibold text-[#172b4d]"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#e9edff] text-it-primary"><i class="pi pi-key" aria-hidden="true"></i></span>Acesso à plataforma do PDV</h2><span class="rounded-full bg-[#e9edff] px-3 py-1 text-xs font-semibold text-it-primary">Administrador do PDV</span></div>
        <p class="text-[#52667f]">O PDV tem seu próprio acesso de administrador. Você cadastra o e-mail e a senha aqui, sem criar outro usuário.</p>
        <p v-if="editing && !configured" class="rounded-xl border border-[#f1d59b] bg-[#fff8e6] p-3 text-sm text-[#70551f]">Este PDV ainda não possui acesso. Informe e-mail e senha para criar seu administrador ao salvar.</p>
        <FormInputText v-model="email" id="pdv-access-email" label="E-mail de acesso do PDV" type="email" autocomplete="username" placeholder="pdv@exemplo.com.br" showIcon icon="pi pi-envelope" wrapper-class="w-full" input-class="w-full" :disabled="disabled" required />
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div><InputPassword v-model="password" id="pdv-access-password" :label="configured ? 'Nova senha (opcional)' : 'Senha inicial'" autocomplete="new-password" wrapper-class="w-full" input-class="w-full" :required="!configured" :disabled="disabled" /><PasswordStrengthIndicator v-if="password" :password="password" /></div>
            <InputPassword v-model="confirmation" id="pdv-access-confirmation" :label="configured ? 'Confirmar nova senha' : 'Confirmar senha inicial'" autocomplete="new-password" wrapper-class="w-full" input-class="w-full" :required="!configured || Boolean(password)" :disabled="disabled" />
        </div>
        <p class="text-sm text-[#52667f]"><span v-if="configured">Deixe a senha em branco para manter a atual. </span>A senha deve ter pelo menos 8 caracteres. Depois, o responsável pode usar “Esqueceu sua senha?” na página de login para redefini-la por e-mail.</p>
    </section>
</template>
