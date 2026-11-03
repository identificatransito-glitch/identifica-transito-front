<script setup lang="ts">
import { QrCode, ShieldCheck, MapPin, ArrowLeft, CheckCircle2 } from '@lucide/vue';
import { getUserType, getDashboardByType } from '~/utils/authRedirect';
definePageMeta({ layout: false });
useHead({ title: 'Conferir retirada | Identifica Trânsito', meta: [{ name: 'robots', content: 'noindex, nofollow' }, { name: 'referrer', content: 'no-referrer' }] });
const auth = useAuth(), config = useRuntimeConfig();
const token = ref(''), input = ref(''), error = ref(''), busy = ref(false), checked = ref<any>(null), delivered = ref(false), confirmed = ref(false), pointId = ref('');
const points = ref<any[]>([]), mounted = ref(false);
const userType = computed(() => getUserType(auth.user.value));
const isStaff = computed(() => auth.isAuthenticated.value && [1, 2].includes(userType.value));
const dashboard = computed(() => getDashboardByType(userType.value));
watch([input, pointId], () => { checked.value = null; confirmed.value = false; error.value = ''; });
onMounted(async () => {
  auth.init(); mounted.value = true;
  const hash = location.hash.slice(1);
  const pending = sessionStorage.getItem('it_pickup_pending') || '';
  if (/^[a-f0-9]{64}$/.test(hash)) input.value = hash;
  else if (/^[a-f0-9]{64}$/.test(pending)) input.value = pending;
  sessionStorage.removeItem('it_pickup_pending');
  if (location.hash) history.replaceState(null, '', location.pathname);
  if (isStaff.value && userType.value === 1) {
    try {
      const res: any = await request('/points-of-sale?perpage=999', 'GET');
      const data = res?.data || res;
      points.value = (Array.isArray(data) ? data : data?.data || []).filter((point: any) => point.active !== false && point.active !== 0);
    } catch { error.value = 'Não foi possível carregar os pontos. Tente entrar novamente.'; }
  }
});
function normalizedCode() {
  const value = input.value.trim();
  if (/^[a-f0-9]{64}$/.test(value)) return value;
  try { const url = new URL(value); if (url.origin === location.origin && /^\/retirada\/?$/.test(url.pathname) && /^[a-f0-9]{64}$/.test(url.hash.slice(1))) return url.hash.slice(1); } catch {}
  return '';
}
function login() {
  const code = normalizedCode();
  if (code) sessionStorage.setItem('it_pickup_pending', code);
  navigateTo('/auth/login', { external: true });
}
async function request(path: string, method: 'GET' | 'POST', body?: any) {
  return await $fetch(path, { baseURL: config.public.apiBase, method, body, headers: { Accept: 'application/json', Authorization: `Bearer ${auth.getToken()}` } });
}
async function verify() {
  error.value = ''; checked.value = null; confirmed.value = false;
  token.value = normalizedCode();
  if (!token.value) { error.value = 'Escaneie um QR de retirada ou informe um código válido.'; return; }
  busy.value = true;
  try { checked.value = await request('/pickup-receipts/verify', 'POST', { token: token.value, point_id: pointId.value || undefined }); }
  catch (err: any) { error.value = err?.data?.message || 'Não foi possível confirmar o pagamento. Não entregue o kit.'; }
  finally { busy.value = false; }
}
async function collect() {
  if (!checked.value || !confirmed.value || busy.value) return;
  busy.value = true; error.value = '';
  try {
    checked.value = await request('/pickup-receipts/collect', 'POST', { token: token.value, point_id: pointId.value || undefined });
    delivered.value = true; token.value = ''; input.value = ''; sessionStorage.removeItem('it_pickup_pending');
  } catch (err: any) { checked.value = null; confirmed.value = false; error.value = err?.data?.message || 'A entrega não foi registrada. Verifique novamente antes de entregar.'; }
  finally { busy.value = false; }
}
</script>
<template>
  <div class="pickup-page">
    <header><a href="/" class="pickup-brand"><img src="/favicon-it.svg" width="35" height="35" alt="" /><span>Identifica<br />Trânsito</span></a><a :href="dashboard"><ArrowLeft :size="17" /> Meu painel</a></header>
    <main>
      <div class="pickup-symbol"><QrCode :size="33" /></div>
      <p class="pickup-eyebrow">PONTOS DE RETIRADA</p>
      <h1>Um kit.<br />Um novo sinal de cuidado.</h1>
      <p class="pickup-lead">Confira o pagamento e registre a entrega com o QR Code do cliente.</p>
      <section class="pickup-panel">
        <p v-if="!mounted" role="status">Carregando…</p>
        <template v-else-if="!auth.isAuthenticated.value"><ShieldCheck :size="32" /><h2>Entre com a conta do ponto.</h2><p>A conferência é exclusiva para a equipe autorizada. O código será mantido enquanto você entra.</p><button type="button" @click="login">Entrar para conferir</button></template>
        <template v-else-if="!isStaff"><h2>Seu comprovante está no painel.</h2><p>Apresente o QR Code do pedido à equipe de um ponto autorizado. A equipe confirma o pagamento e registra a entrega.</p><a href="/dashboard/pedidos">Acessar meus pedidos →</a></template>
        <template v-else-if="delivered"><CheckCircle2 class="pickup-success" :size="44" /><h2>Retirada registrada.</h2><p>Este comprovante foi utilizado e não permite outra retirada.</p><a :href="dashboard">Voltar ao painel →</a></template>
        <template v-else>
          <h2>Conferir comprovante</h2>
          <p>Escaneie o QR com a câmera do celular ou cole o link do comprovante abaixo.</p>
          <label v-if="userType === 1" for="pickup-point">Ponto onde a entrega será feita</label>
          <select v-if="userType === 1" id="pickup-point" v-model="pointId"><option value="">Selecione o ponto</option><option v-for="point in points" :key="point.id" :value="String(point.id)">{{ point.name }}</option></select>
          <label for="pickup-code">Código ou link do comprovante</label>
          <input id="pickup-code" v-model="input" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Cole o comprovante do cliente" :disabled="busy" />
          <button type="button" @click="verify" :disabled="busy || !input.trim() || (userType === 1 && !pointId)">{{ busy ? 'Conferindo…' : 'Conferir pagamento' }}</button>
          <p v-if="error" class="pickup-error" role="alert">{{ error }}</p>
          <div v-if="checked" class="pickup-verified" role="status">
            <p class="pickup-paid"><ShieldCheck :size="23" />{{ checked.payment_status === 'exempt' ? 'Pedido isento aprovado' : 'Pagamento aprovado' }}</p>
            <dl><div><dt>Pedido</dt><dd>#{{ checked.order_id }}</dd></div><div><dt>Kit</dt><dd>{{ checked.tag_name }} · {{ checked.quantity }} un.</dd></div><div><dt>Retirada em</dt><dd>{{ checked.point_name }}</dd></div></dl>
            <label class="pickup-confirm"><input v-model="confirmed" type="checkbox" :disabled="busy" /><span>Conferi o kit e vou entregá-lo ao portador deste comprovante.</span></label>
            <button type="button" @click="collect" :disabled="busy || !confirmed">{{ busy ? 'Registrando…' : 'Confirmar entrega do kit' }}</button>
          </div>
        </template>
      </section>
      <p class="pickup-footer"><MapPin :size="17" /> Válido em qualquer ponto autorizado. Uma entrega por comprovante.</p>
    </main>
  </div>
</template>
<style scoped>
.pickup-page{min-height:100dvh;background:#f7f7fc;color:#1a2033;font-family:Figtree,sans-serif;padding:0 24px 32px}.pickup-page header{max-width:1180px;margin:auto;display:flex;justify-content:space-between;align-items:center;padding:26px 0;border-bottom:1px solid #e6e7ef}.pickup-page a{color:#2920d8;text-decoration:none;font-size:15px;font-weight:600;display:inline-flex;align-items:center;gap:8px}.pickup-brand{color:#202339!important;font-size:17px!important;line-height:1.1}.pickup-page main{max-width:550px;margin:55px auto 0}.pickup-symbol{width:64px;height:64px;border-radius:18px;background:#e9e7ff;color:#2920d8;display:grid;place-items:center}.pickup-eyebrow{font-size:11px;letter-spacing:2px;font-weight:800;color:#2920d8;margin:24px 0 13px}.pickup-page h1{font-size:44px;line-height:1.08;letter-spacing:-1.7px;margin:0}.pickup-lead{font-size:18px;line-height:1.6;color:#687086;margin:20px 0 30px}.pickup-panel{padding:28px;background:#fff;border:1px solid #e5e5ef;border-radius:22px}.pickup-panel h2{font-size:24px;letter-spacing:-.6px;margin:0 0 12px}.pickup-panel p{font-size:15px;line-height:1.65;color:#687086}.pickup-panel label:not(.pickup-confirm){display:block;margin:24px 0 9px;font-size:14px;font-weight:650}.pickup-panel input:not([type=checkbox]),.pickup-panel select{box-sizing:border-box;width:100%;padding:15px 13px;border:1px solid #dadde9;border-radius:11px;background:#fafafe;font:15px Figtree,sans-serif;color:#1a2033;min-height:50px}.pickup-panel button{width:100%;padding:16px;border:0;border-radius:11px;background:#2920db;color:#fff;font:650 15px Figtree,sans-serif;margin-top:20px;cursor:pointer}.pickup-panel button:disabled{opacity:.5;cursor:not-allowed}.pickup-panel input:focus,.pickup-panel select:focus{outline:2px solid #817bea;outline-offset:2px}.pickup-error{color:#a52323!important;background:#fff3f3;padding:12px;border-radius:10px}.pickup-verified{margin-top:24px;border-top:1px solid #e8e9ef;padding-top:15px}.pickup-paid{display:flex;align-items:center;gap:9px;color:#13845c!important;font-weight:700}.pickup-verified dl{font-size:14px;line-height:1.5}.pickup-verified dl div{display:flex;justify-content:space-between;gap:15px;padding:9px 0}.pickup-verified dt{color:#687086}.pickup-verified dd{margin:0;font-weight:650;text-align:right}.pickup-confirm{display:flex;align-items:flex-start;gap:12px;font-size:14px;line-height:1.5;padding:15px;border-radius:10px;background:#f5f5fc}.pickup-confirm input{margin-top:4px;accent-color:#2920db}.pickup-success{color:#13845c;margin-bottom:15px}.pickup-footer{display:flex;gap:7px;justify-content:center;color:#787e91;font-size:12px;line-height:1.6;margin:24px 0}.pickup-footer svg{flex-shrink:0;margin-top:2px}
@media(max-width:540px){.pickup-page{padding:0 18px 25px}.pickup-page header{padding:20px 0}.pickup-page main{margin-top:32px}.pickup-page h1{font-size:36px}.pickup-panel{padding:22px}.pickup-lead{font-size:16px}}
</style>
