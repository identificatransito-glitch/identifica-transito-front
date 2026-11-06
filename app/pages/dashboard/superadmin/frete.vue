<script setup lang="ts">
import { useToast } from 'primevue/usetoast';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import ShippingConfiguration from '~/components/shipping/ShippingConfiguration.vue';
definePageMeta({layout:'dashboard'});
useHead({title:'Frete - Super Admin | Identifica Trânsito'});
const {$api}=useNuxtApp();const toast=useToast();
const form=ref({enabled:false,fixed_amount:0});
const loading=ref(true),saving=ref(false),error=ref(false);
const valid=computed(()=>Number.isFinite(Number(form.value.fixed_amount))&&Number(form.value.fixed_amount)>=0&&Number(form.value.fixed_amount)<=9999.99&&Math.abs(Number(form.value.fixed_amount)*100-Math.round(Number(form.value.fixed_amount)*100))<.00001);
const money=(n:number)=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(n);
async function load(){loading.value=true;error.value=false;try{const response:any=await $api('/admin/shipping-settings');const data=response?.data??response;form.value.enabled=data.enabled;form.value.fixed_amount=Number(data.fixed_amount);}catch{error.value=true;}finally{loading.value=false;}}
async function save(){if(!valid.value||loading.value||error.value||saving.value)return;saving.value=true;try{await $api('/admin/shipping-settings',{method:'PUT',body:{enabled:form.value.enabled,fixed_amount:Number(form.value.fixed_amount)}});toast.add({severity:'success',summary:'Frete atualizado',detail:form.value.enabled?'A cobrança de frete está habilitada para entrega em casa.':'Frete desativado. As compras continuam sem cobrança de frete.',life:5000});}catch(e:any){toast.add({severity:'error',summary:'Não foi possível salvar',detail:e?.data?.message||'Tente novamente.',life:5000});}finally{saving.value=false;}}
onMounted(load);
</script>
<template><div class="space-y-6"><HeroSection title="Frete" subtitle="Defina a cobrança de entrega dos kits. Retirada em PDV continua gratuita." greeting="Comercial" /><ShippingConfiguration v-model="form" :loading="loading" :saving="saving" :error="error" @save="save" @retry="load" /><section class="max-w-3xl rounded-2xl border border-[#e1e6ee] bg-white p-6 text-[#52667f]"><h2 class="mb-2 text-lg font-semibold text-[#172b4d]">Cotação automática por CEP</h2><p>O valor configurado acima é fixo e não varia pelo CEP. Para cotar por destino, será necessário conectar uma conta do Melhor Envio e informar os dados da embalagem. Essa integração ainda não está ativa.</p><p class="mt-2">A integração não exige mensalidade, mas cada postagem tem seu custo.</p></section></div></template>
