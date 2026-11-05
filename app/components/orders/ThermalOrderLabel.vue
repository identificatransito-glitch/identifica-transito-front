<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { pickupPaymentLabel, pickupOrderDate } from '~/utils/pickupOrderInfo';
const props=defineProps<{order:any;pickup:boolean;point:any;recipient:any;senderName:string;senderAddress:string;fixedFontScale?:number}>();
const emit=defineEmits<{fit:[boolean];busy:[boolean];scale:[number]}>();
const label=ref<HTMLElement|null>(null),content=ref<HTMLElement|null>(null),fontScale=ref(1);
const prettyZip=(value:string)=>{const digits=(value||'').replace(/\D/g,'');return digits.length===8?`${digits.slice(0,5)}-${digits.slice(5)}`:value;};
let version=0;
async function fit(){
 const run=++version;await nextTick();
 if(!label.value?.getClientRects().length||!content.value)return;
 emit('busy',true);if(document.fonts)await document.fonts.ready;
 if(run!==version||!content.value)return;
 for(const scale of [1,.96,.92,.88,.84,.8]){
  if(run!==version)return;fontScale.value=scale;emit('scale',scale);await nextTick();if(run!==version||!content.value)return;
  if(content.value.scrollHeight<=content.value.clientHeight+1&&content.value.scrollWidth<=content.value.clientWidth+1){emit('fit',true);emit('busy',false);return;}
 }
 emit('fit',false);emit('busy',false);
}
watch(()=>[props.order,props.pickup,props.point,props.recipient,props.senderName,props.senderAddress],fit,{deep:true});
onMounted(fit);
onBeforeUnmount(()=>version++);
</script>
<template>
 <article ref="label" class="thermal-order-label" :style="{'--thermal-font-scale':fixedFontScale ?? fontScale}" :aria-label="pickup?'Identificação de retirada 100 por 150 milímetros':'Endereçamento 100 por 150 milímetros'">
  <div ref="content" class="thermal-label-content">
   <header class="thermal-label-header"><strong>IDENTIFICA TRÂNSITO</strong><span>{{ pickup?'IDENTIFICAÇÃO PARA RETIRADA':'ETIQUETA DE ENDEREÇAMENTO' }}</span><b>Pedido #{{ order.id }}</b></header>
   <template v-if="pickup">
    <section class="thermal-label-section"><span class="thermal-caption">CLIENTE</span><strong class="thermal-name">{{ order.user_name || order.user?.name || 'Nome não informado' }}</strong><p v-if="order.vehicle_plate || order.vehicle?.plate">Placa: <b>{{ order.vehicle_plate || order.vehicle?.plate }}</b></p><p>{{ order.tag_name || 'Kit de etiquetas' }} · {{ order.qty }} {{ Number(order.qty)===1?'kit':'kits' }}</p><p v-if="order.created_at" class="thermal-small">Pedido em {{ pickupOrderDate(order.created_at) }}</p></section>
    <section class="thermal-label-section"><span class="thermal-caption">PONTO DE PREFERÊNCIA</span><strong class="thermal-name">{{ point.name }}</strong><p>{{ point.address || 'Endereço não disponível. Consulte o ponto antes de ir.' }}</p><p v-if="point.responsible">Responsável: {{ point.responsible }}</p><p v-if="point.phone">Telefone: {{ point.phone }}</p></section>
    <section class="thermal-label-section thermal-payment"><span>Pagamento informado no pedido</span><b>{{ pickupPaymentLabel(order) }}</b></section>
    <section class="thermal-label-notes"><p>Retirada em qualquer PDV autorizado, conforme disponibilidade. O ponto acima é a preferência do pedido.</p><p><b>Confira o QR do cliente antes de entregar.</b> Esta identificação não comprova pagamento nem entrega.</p></section>
   </template>
   <template v-else>
    <section class="thermal-label-section"><span class="thermal-caption">DESTINATÁRIO</span><strong class="thermal-name">{{ recipient.name }}</strong><p>{{ recipient.street }}<template v-if="recipient.number">, {{ recipient.number }}</template></p><p v-if="recipient.complement">{{ recipient.complement }}</p><p v-if="recipient.neighborhood">{{ recipient.neighborhood }}</p><p>{{ recipient.city }} / {{ (recipient.state||'').toUpperCase() }}</p><b class="thermal-postcode">CEP {{ prettyZip(recipient.zip) }}</b></section>
    <section class="thermal-label-section"><span class="thermal-caption">REMETENTE</span><strong>{{ senderName }}</strong><p>{{ senderAddress }}</p></section>
    <section class="thermal-label-section thermal-small"><p>{{ order.tag_name || 'Kit de etiquetas' }} · {{ order.qty }} {{ Number(order.qty)===1?'kit':'kits' }}<template v-if="order.vehicle_plate || order.vehicle?.plate"> · Placa {{ order.vehicle_plate || order.vehicle?.plate }}</template></p></section>
    <section class="thermal-label-notes"><p>Endereçamento da embalagem. Não substitui a etiqueta oficial de postagem emitida pelos Correios ou transportadora.</p></section>
   </template>
   <footer class="thermal-label-footer">IDENTIFICA TRÂNSITO · Pedido #{{ order.id }} · 100 × 150 mm</footer>
  </div>
 </article>
</template>
<style>
.thermal-order-label{width:100mm;height:149.8mm;box-sizing:border-box;padding:4mm;background:#fff!important;color:#000!important;font-family:Arial,sans-serif!important;line-height:1.2;overflow:visible;text-align:left;flex-shrink:0}.thermal-label-content{height:100%;width:100%;box-sizing:border-box;display:flex;flex-direction:column;overflow:visible;font-size:calc(9.5pt * var(--thermal-font-scale,1));overflow-wrap:anywhere;word-break:normal}.thermal-label-header{display:flex;flex-direction:column;gap:1.8mm;padding-bottom:3mm;border-bottom:.25mm solid #000}.thermal-label-header>strong{font-size:calc(12pt * var(--thermal-font-scale,1));line-height:1.1}.thermal-label-header>span{font-size:calc(7.5pt * var(--thermal-font-scale,1));font-weight:700;letter-spacing:.15mm}.thermal-label-header>b{font-size:calc(11pt * var(--thermal-font-scale,1))}.thermal-label-section{display:flex;flex-direction:column;gap:1.2mm;padding:3mm 0;border-bottom:.2mm solid #000;min-width:0}.thermal-order-label .thermal-label-content p{margin:0!important;padding:0!important;color:#000!important;font:inherit!important;line-height:1.2!important}.thermal-caption{font-size:calc(7.5pt * var(--thermal-font-scale,1));font-weight:700;letter-spacing:.15mm}.thermal-name{font-size:calc(11pt * var(--thermal-font-scale,1));line-height:1.2}.thermal-postcode{font-size:calc(15pt * var(--thermal-font-scale,1));letter-spacing:.3mm;margin-top:1.2mm}.thermal-small{font-size:calc(8pt * var(--thermal-font-scale,1))!important}.thermal-label-notes{display:flex;flex-direction:column;gap:2mm;padding:3mm 0;font-size:calc(7.3pt * var(--thermal-font-scale,1));line-height:1.2}.thermal-label-footer{margin-top:auto;border-top:.2mm solid #000;padding-top:2mm;font-size:calc(7pt * var(--thermal-font-scale,1))}.thermal-order-label *{box-shadow:none!important;text-shadow:none!important;color:#000!important;border-radius:0!important}.thermal-payment{font-size:calc(8.5pt * var(--thermal-font-scale,1))}.thermal-payment b{font-size:calc(10pt * var(--thermal-font-scale,1))}
@page it-thermal-label{size:100mm 150mm;margin:0}
@media print{body.it-print-shipping{margin:0!important;padding:0!important;min-width:0!important;background:#fff!important}body.it-print-shipping>*{display:none!important}body.it-print-shipping>#it-shipping-print{display:block!important;position:static!important;width:100mm!important;height:149.8mm!important;margin:0!important;padding:0!important;border:0!important;page:it-thermal-label;break-inside:avoid;break-after:avoid}#it-shipping-print .thermal-order-label{page:it-thermal-label;transform:none!important;margin:0!important}#it-shipping-print a{color:#000!important;text-decoration:none!important}}
</style>
