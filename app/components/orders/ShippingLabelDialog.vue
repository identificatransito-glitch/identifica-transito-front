<script setup lang="ts">
import { Printer, X, Package, MapPin } from '@lucide/vue';
import ThermalOrderLabel from '~/components/orders/ThermalOrderLabel.vue';
import PickupOrderDetails from '~/components/orders/PickupOrderDetails.vue';
import { pickupOrderInfo } from '~/utils/pickupOrderInfo';
import PdvAddressFields from '~/components/forms/PdvAddressFields.vue';
const props = defineProps<{ order: any | null }>();
const emit = defineEmits<{ (event: 'close'): void }>();
const auth = useAuth(), { $api } = useNuxtApp();
const senderName = ref('Identifica Trânsito'), senderAddress = ref(''), senderBusy = ref(false), senderValid = ref(true);
const origin = ref(''), points = ref<any[]>([]);
const recipient = ref({ name: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '', zip: '' });
const previewBox=ref<HTMLElement|null>(null),previewScale=ref(.85),labelFits=ref(false),labelFitting=ref(true),printError=ref('');
let previewObserver:ResizeObserver|undefined;
const resizePreview=()=>{if(previewBox.value)previewScale.value=Math.min(1,previewBox.value.clientWidth/(100*96/25.4));};
watch(previewBox, box=>{previewObserver?.disconnect();if(box){previewObserver=new ResizeObserver(resizePreview);previewObserver.observe(box);resizePreview();}});
const isPickup = computed(() => Boolean(props.order?.pickup_point));
const pickupPoint = computed(() => pickupOrderInfo(props.order,points.value));
const pointsLoading=ref(false),pointsError=ref(false);
async function fetchPoints(){
 pointsLoading.value=true;pointsError.value=false;
 try{const result:any=await $api('/points-of-sale?perpage=999');const data=result?.data||result;points.value=Array.isArray(data)?data:data?.data||[];}catch{pointsError.value=true;}finally{pointsLoading.value=false;}
}
const zipDigits = computed(() => recipient.value.zip.replace(/\D/g, ''));
const prettyZip = (value: string) => { const digits = value.replace(/\D/g, ''); return digits.length === 8 ? `${digits.slice(0,5)}-${digits.slice(5)}` : value; };
const ready = computed(() => isPickup.value || Boolean(recipient.value.name.trim() && recipient.value.street.trim() && recipient.value.number.trim() && recipient.value.city.trim() && /^[A-Za-z]{2}$/.test(recipient.value.state.trim()) && zipDigits.value.length === 8 && senderName.value.trim() && senderAddress.value.trim() && /CEP \d{5}-\d{3}/.test(senderAddress.value) && / — [^—]+\/[A-Z]{2} — CEP/.test(senderAddress.value) && senderAddress.value.includes(' — nº ') && senderValid.value && !senderBusy.value));
watch(() => props.order, async (order) => {
  if (!order) return;
  const d = order.delivery || {};
  recipient.value = { name: order.user_name || '', street: d.street || '', number: d.number || '', complement: d.complement || '', neighborhood: d.neighborhood || '', city: d.city || '', state: d.state || '', zip: d.zip_code || '' };
  if (points.value.length === 0) {
    await fetchPoints();
  }
  const own = Number(auth.user.value?.pdv_id || 0);
  const point = points.value.find(point => Number(point.id) === own);
  if (point) { origin.value=String(point.id); senderName.value=point.name; senderAddress.value=point.address||''; }
}, { immediate: true });
watch(origin, value => { const point=points.value.find(point=>String(point.id)===value); if(point){ senderName.value=point.name; senderAddress.value=point.address||''; } });
function finishPrint() { document.body.classList.remove('it-print-shipping');document.getElementById('it-thermal-page-style')?.remove(); }
async function print() {
 if(!ready.value || !labelFits.value || labelFitting.value)return;printError.value='';await nextTick();if(document.fonts)await document.fonts.ready;await nextTick();if(!labelFits.value || labelFitting.value)return;
 const page=document.createElement('style');page.id='it-thermal-page-style';page.textContent='@page { size:100mm 150mm; margin:0; }';document.getElementById(page.id)?.remove();document.head.appendChild(page);
 document.body.classList.add('it-print-shipping');await nextTick();
 try{window.print();}catch{finishPrint();printError.value='Não foi possível abrir a impressão. Tente novamente.';}
}
function close() { finishPrint(); emit('close'); }
onMounted(() => window.addEventListener('afterprint', finishPrint));
onBeforeUnmount(() => { previewObserver?.disconnect();finishPrint(); window.removeEventListener('afterprint', finishPrint); });
</script>
<template>
  <Teleport to="body">
    <div v-if="order" class="shipping-overlay" @click.self="close">
      <section class="shipping-dialog" role="dialog" aria-modal="true" aria-labelledby="shipping-title">
        <header><div><p>EXPEDIÇÃO · IDENTIFICA TRÂNSITO</p><h2 id="shipping-title">{{ isPickup ? 'Identificação do kit para retirada' : 'Etiqueta para a embalagem' }}</h2></div><button type="button" aria-label="Fechar impressão" @click="close"><X :size="22" /></button></header>
        <div class="shipping-body">
          <div class="shipping-fields">
            <template v-if="!isPickup">
              <h3>Destinatário</h3>
              <p>Dados do endereço de entrega deste pedido. Confira antes de imprimir.</p>
              <label>Nome<input v-model="recipient.name" autocomplete="off" /></label>
              <div class="shipping-grid"><label>CEP<input v-model="recipient.zip" inputmode="numeric" maxlength="9" /></label><label>Número<input v-model="recipient.number" /></label></div>
              <label>Rua / Avenida<input v-model="recipient.street" /></label>
              <div class="shipping-grid"><label>Bairro<input v-model="recipient.neighborhood" /></label><label>Complemento<input v-model="recipient.complement" /></label></div>
              <div class="shipping-grid"><label>Cidade<input v-model="recipient.city" /></label><label>Estado (UF)<input v-model="recipient.state" maxlength="2" /></label></div>
              <h3>Remetente</h3>
              <label v-if="points.length">Origem da postagem<select v-model="origin"><option value="">Informar manualmente</option><option v-for="point in points" :key="point.id" :value="String(point.id)">{{ point.name }}</option></select></label>
              <label>Nome / Razão social<input v-model="senderName" autocomplete="organization" /></label>
              <PdvAddressFields v-model="senderAddress" @busy="senderBusy = $event" @validity="senderValid = $event" />
              <p class="shipping-help">Os ajustes desta prévia valem apenas para a impressão.</p>
              <p class="shipping-help">Esta é a etiqueta de endereçamento. A postagem e o documento fiscal (NF-e ou DC-e, conforme o caso) são emitidos separadamente pelos serviços correspondentes. <a href="https://www.correios.com.br/enviar/encomendas/nacional" target="_blank" rel="noopener noreferrer">Orientações dos Correios ↗</a></p>
            </template>
            <template v-else><p v-if="pointsLoading" role="status">Carregando informações do ponto…</p><p v-if="pointsError" role="status">Não foi possível carregar todos os dados do ponto. <button type="button" class="shipping-retry" @click="fetchPoints">Tentar novamente</button></p><PickupOrderDetails :order="order" :point="pickupPoint" /></template>
          </div>
          <div class="shipping-preview"><p class="shipping-preview-caption">ETIQUETA TÉRMICA · 100 × 150 mm</p><div ref="previewBox" class="thermal-preview-box" :style="{height:`${149.8*96/25.4*previewScale}px`}"><div class="thermal-preview-paper" :style="{transform:`scale(${previewScale})`}"><ThermalOrderLabel :order="order" :pickup="isPickup" :point="pickupPoint" :recipient="recipient" :sender-name="senderName" :sender-address="senderAddress" @fit="labelFits=$event" @busy="labelFitting=$event" /></div></div></div>
        </div>
        <div class="thermal-print-hints"><strong>Impressão térmica 10 × 15 cm</strong><span>Selecione papel 100 × 150 mm, escala 100%, margens sem borda e desative cabeçalhos e rodapés no diálogo da impressora.</span><p v-if="!labelFits && !labelFitting" role="alert">Os dados ultrapassam uma etiqueta. Abrevie os endereços ou complementos antes de imprimir; nenhuma informação será cortada.</p><p v-if="printError" role="alert">{{ printError }}</p></div>
        <footer class="shipping-actions"><p v-if="!ready && !isPickup">Preencha o destinatário e o remetente com CEP para imprimir.</p><button type="button" @click="close">Fechar</button><button type="button" class="shipping-print-button" :disabled="!ready || !labelFits || labelFitting" @click="print"><Printer :size="19" /> {{ isPickup ? 'Imprimir identificação' : 'Imprimir etiqueta' }}</button></footer>
      </section>
    </div>
    <article v-if="order" id="it-shipping-print"><ThermalOrderLabel :order="order" :pickup="isPickup" :point="pickupPoint" :recipient="recipient" :sender-name="senderName" :sender-address="senderAddress" /></article>
  </Teleport>
</template>
<style>
#it-shipping-print{display:none}.thermal-preview-box{position:relative;max-width:100%;min-width:0;margin:auto;overflow:visible}.thermal-preview-paper{width:100mm;position:absolute;top:0;left:0;transform-origin:top left;background:white;outline:1px solid #cbd0db}.thermal-print-hints{display:flex;flex-direction:column;gap:5px;border-top:1px solid #e5e7ef;padding:15px 26px;font:13px/1.5 Figtree,sans-serif;color:#52617b}.thermal-print-hints strong{color:#1b2237}.thermal-print-hints p{margin:0;color:#a12b23}.shipping-overlay{position:fixed;inset:0;background:#071126ad;z-index:10000;padding:22px;display:grid;place-items:center}.shipping-dialog{font-family:Figtree,sans-serif;background:#fff;border-radius:20px;max-width:1100px;width:100%;max-height:calc(100dvh - 44px);overflow:auto;color:#1b2237}.shipping-dialog>header{display:flex;justify-content:space-between;align-items:flex-start;padding:25px;border-bottom:1px solid #e5e7ef}.shipping-dialog header p{font-size:10px;letter-spacing:1.5px;font-weight:700;color:#2820d9;margin:0 0 8px}.shipping-dialog h2{font-size:25px;margin:0;letter-spacing:-.7px}.shipping-dialog header button{background:transparent;border:0;cursor:pointer}.shipping-body{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:30px;padding:26px}.shipping-fields{min-width:0}.shipping-fields h3{font-size:18px;margin:24px 0 12px}.shipping-fields h3:first-child{margin-top:0}.shipping-fields p{font-size:14px;line-height:1.5;color:#687086}.shipping-fields>label,.shipping-grid>label{display:block;font-size:13px;font-weight:600;margin:14px 0;color:#34445b}.shipping-fields>label input,.shipping-grid>label input,.shipping-fields>label select{display:block;width:100%;box-sizing:border-box;min-height:44px;padding:10px;border:1px solid #d9ddeb;border-radius:9px;margin-top:7px;font:15px Figtree,sans-serif;color:#1b2237;background:#fff}.shipping-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.shipping-preview{background:#f5f5fa;border-radius:14px;padding:18px;align-self:start;position:sticky;top:15px}.shipping-preview-caption{font:700 10px Figtree,sans-serif;letter-spacing:1.4px;color:#73798a;margin:0 0 15px}.shipping-label-preview{min-height:450px;border:1px solid #222;background:white;color:#000;font:14px/1.4 Arial,sans-serif;overflow-wrap:anywhere;display:flex;flex-direction:column}.shipping-reserved{border-bottom:1px solid #111;min-height:90px;text-align:center;font:700 10px/1.6 Arial,sans-serif;padding:12px;box-sizing:border-box}.shipping-reserved small{font-weight:400}.shipping-destination{padding:17px;display:grid;gap:3px}.shipping-destination small,.shipping-sender small{font-size:10px;font-weight:700;letter-spacing:1px}.shipping-destination strong{font-size:18px;margin:5px 0}.shipping-destination b{font-size:18px;letter-spacing:1px;margin-top:8px}.shipping-sender{border-top:1px solid #111;padding:13px 17px;display:grid;gap:4px;font-size:12px}.shipping-label-preview>footer{padding:10px 15px;border-top:1px solid #111;font-size:10px;margin-top:auto}.shipping-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px;padding:20px 25px;border-top:1px solid #e5e7ef;align-items:center}.shipping-actions>p{font-size:12px;color:#966414;margin:0 auto 0 0}.shipping-actions button{border:1px solid #d9ddeb;padding:12px 17px;border-radius:10px;background:white;font:600 14px Figtree,sans-serif;cursor:pointer}.shipping-actions .shipping-print-button{display:inline-flex;align-items:center;gap:9px;color:white;background:#2820d9;border-color:#2820d9}.shipping-actions button:disabled{opacity:.45;cursor:not-allowed}.shipping-help{font-size:12px!important}.shipping-help a{color:#2820d9}.shipping-retry{border:0;background:transparent;color:#2820d9;cursor:pointer;text-decoration:underline;font:inherit}
@media(max-width:800px){.shipping-body{grid-template-columns:1fr;padding:20px}.shipping-preview{position:static}.shipping-overlay{padding:10px}.shipping-dialog{max-height:calc(100dvh - 20px)}.shipping-grid{gap:10px}.shipping-dialog h2{font-size:22px}}

</style>
