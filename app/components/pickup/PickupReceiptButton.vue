<script setup lang="ts">
import QRCode from 'qrcode';
import { QrCode, X, MapPin, CheckCircle2 } from '@lucide/vue';
const props = defineProps<{ orderId: number; quantity?: number }>();
const { $api } = useNuxtApp();
const opened = ref(false), loading = ref(false), error = ref(''), image = ref('');
const receipt = ref<any>(null);
async function open() {
  opened.value = true; loading.value = true; error.value = ''; image.value = ''; receipt.value = null;
  try {
    const data: any = await $api(`/orders/${props.orderId}/pickup-receipt`, { method: 'POST' });
    receipt.value = data;
    if (!data.collected_at) image.value = await QRCode.toDataURL(data.receipt_url, {
      width: 320, margin: 4, errorCorrectionLevel: 'M', color: { dark: '#111827', light: '#ffffff' }
    });
  } catch (err: any) { error.value = err?.data?.message || 'Não foi possível abrir seu comprovante. Tente novamente.'; }
  finally { loading.value = false; }
}
function close() { opened.value = false; receipt.value = null; image.value = ''; }
function escape(event: KeyboardEvent) { if (event.key === 'Escape') close(); }
onMounted(() => document.addEventListener('keydown', escape));
onBeforeUnmount(() => document.removeEventListener('keydown', escape));
</script>
<template>
  <button type="button" class="pickup-open" @click="open"><QrCode :size="20" /> QR de retirada <span v-if="quantity && quantity > 1">({{ quantity }} kits)</span></button>
  <Teleport to="body">
    <div v-if="opened" class="pickup-backdrop" @click.self="close">
      <section class="pickup-dialog" role="dialog" aria-modal="true" :aria-labelledby="`pickup-title-${orderId}`" tabindex="-1">
        <button type="button" class="pickup-close" aria-label="Fechar comprovante" @click="close"><X :size="22" /></button>
        <p class="pickup-eyebrow">IDENTIFICA TRÂNSITO</p>
        <h2 :id="`pickup-title-${orderId}`">Seu kit. No seu caminho.</h2>
        <p class="pickup-intro">Apresente este comprovante em qualquer ponto autorizado.</p>
        <p v-if="loading" role="status">Preparando seu QR Code…</p>
        <p v-else-if="error" role="alert" class="pickup-error">{{ error }}</p>
        <template v-else-if="receipt">
          <div v-if="receipt.collected_at" class="pickup-collected"><CheckCircle2 :size="38" /><strong>Kit já retirado</strong><span>Este comprovante já foi utilizado.</span></div>
          <template v-else>
            <img v-if="image" :src="image" width="320" height="320" alt="QR Code do comprovante de retirada deste pedido" class="pickup-qr" />
            <p class="pickup-order">Pedido #{{ receipt.order_id }} · {{ receipt.quantity }} {{ receipt.quantity === 1 ? 'kit' : 'kits' }}</p>
            <p v-if="receipt.payment_status !== 'approved'" class="pickup-pending">Aguardando aprovação do pagamento.</p>
            <p class="pickup-note"><MapPin :size="20" /><span>O ponto confere o pagamento no momento da retirada e registra a entrega uma única vez. Consulte a disponibilidade do kit antes de ir.</span></p>
            <p class="pickup-private">Este QR é para retirada, não para pagar. Compartilhe somente com a equipe do ponto.</p>
          </template>
        </template>
      </section>
    </div>
  </Teleport>
</template>
<style scoped>
.pickup-open{display:inline-flex;align-items:center;justify-content:center;gap:9px;border:1px solid #dcdafa;background:#f1f0ff;border-radius:12px;padding:12px 16px;color:#2820d9;font:600 15px/1.4 Figtree,sans-serif;cursor:pointer}.pickup-open:hover{background:#e6e3ff}.pickup-backdrop{position:fixed;inset:0;z-index:10000;background:#071126ac;display:grid;place-items:center;padding:20px;overflow:auto}.pickup-dialog{position:relative;max-width:470px;width:100%;max-height:calc(100dvh - 40px);overflow:auto;border-radius:24px;background:#fff;padding:32px;color:#1b2237;text-align:center;box-shadow:0 24px 100px #0004;font-family:Figtree,sans-serif}.pickup-close{position:absolute;right:14px;top:14px;background:transparent;border:0;padding:7px;cursor:pointer}.pickup-eyebrow{font-size:11px;font-weight:800;letter-spacing:1.6px;color:#2820d9;margin:8px 0 14px}.pickup-dialog h2{font-size:29px;line-height:1.15;letter-spacing:-1px;margin:0 0 12px}.pickup-intro{font-size:16px;line-height:1.6;color:#656b7e;margin:0}.pickup-qr{display:block;width:min(100%,300px);height:auto;margin:10px auto 0}.pickup-order{font-size:15px;font-weight:700;margin:0 0 15px}.pickup-note{display:flex;gap:10px;align-items:flex-start;text-align:left;background:#f5f5fc;padding:15px;border-radius:12px;font-size:14px;line-height:1.6;margin:0}.pickup-note svg{flex-shrink:0;color:#2820d9;margin-top:2px}.pickup-private{font-size:12px;color:#70778a;line-height:1.6;margin:16px 0 0}.pickup-error{color:#a92727;line-height:1.5}.pickup-pending{background:#fff4d9;padding:10px;border-radius:8px;color:#795710;font-size:14px}.pickup-collected{display:grid;justify-items:center;gap:12px;padding:40px 0;color:#13845c}.pickup-collected span{font-size:14px;color:#6b7280}
@media(max-width:480px){.pickup-dialog{padding:30px 20px}.pickup-dialog h2{font-size:26px}}
</style>
