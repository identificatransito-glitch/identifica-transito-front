<script setup lang="ts">
import { MapPin, Search, Check, ArrowUpRight, Store, X, LoaderCircle } from '@lucide/vue';
import { findPickupPoints, regionPriority, regionLabel, pickupMapUrl, zipDigits, type PickupPoint, type PickupRegion } from '~/utils/pickupPoints';
const props = withDefaults(defineProps<{ modelValue: number | null; points: PickupPoint[]; referenceAddress?: PickupRegion | null; loading?: boolean; error?: boolean }>(), { referenceAddress: null, loading: false, error: false });
const emit = defineEmits<{ 'update:modelValue': [number | null]; retry: [] }>();
const query=ref(''), cep=ref(''), region=ref<PickupRegion|null>(null), busy=ref(false), message=ref(''), expanded=ref(false), edited=ref(false);
let controller: AbortController | undefined;
watch(() => props.referenceAddress, address => {
  if (edited.value || !address) return;
  cep.value=address.zip.length===8 ? `${address.zip.slice(0,5)}-${address.zip.slice(5)}` : '';
  region.value=address.city && address.state ? { ...address } : null;
}, { immediate: true, deep: true });
const filtered=computed(()=>findPickupPoints(props.points,query.value,region.value));
const visible=computed(()=>expanded.value?filtered.value:filtered.value.slice(0,6));
const selected=computed(()=>props.points.find(point=>point.id===props.modelValue));
watch([query,region],()=>expanded.value=false);
function changeCep(event: Event) {
  edited.value=true; controller?.abort(); busy.value=false; region.value=null; message.value='';
  const digits=zipDigits((event.target as HTMLInputElement).value).slice(0,8);
  cep.value=digits.length>5?`${digits.slice(0,5)}-${digits.slice(5)}`:digits;
}
async function lookup() {
  const digits=zipDigits(cep.value);
  if (digits.length!==8) { message.value='Informe um CEP com 8 dígitos.'; return; }
  controller?.abort(); const request=new AbortController(); controller=request; busy.value=true; message.value='';
  const timer=setTimeout(()=>request.abort(),8000);
  try {
    const response=await fetch(`https://viacep.com.br/ws/${digits}/json/`, { signal:request.signal, credentials:'omit', referrerPolicy:'no-referrer' });
    if (!response.ok) throw new Error(); const data=await response.json();
    if (controller!==request || digits!==zipDigits(cep.value)) return;
    if (data.erro || !data.localidade || !/^[A-Z]{2}$/.test(data.uf||'')) throw new Error();
    region.value={zip:digits, city:data.localidade,state:data.uf,neighborhood:data.bairro||''};
  } catch {
    if (controller===request && digits===zipDigits(cep.value)) message.value='Não foi possível localizar esse CEP. Confira o número ou busque pelo nome, bairro ou cidade.';
  } finally { clearTimeout(timer); if (controller===request) busy.value=false; }
}
onBeforeUnmount(()=>controller?.abort());
</script>
<template>
  <section class="pickup-finder" aria-labelledby="pickup-finder-title">
    <header class="pickup-finder-heading"><div class="pickup-finder-icon"><MapPin :size="23" /></div><div><h3 id="pickup-finder-title">Encontre um ponto no seu caminho</h3><p>Escolha seu ponto de preferência. A retirada pode ser feita em qualquer PDV autorizado, conforme disponibilidade.</p></div></header>
    <div class="pickup-finder-fields">
      <div><label for="pickup-reference-cep">Seu CEP de referência</label><div class="pickup-cep-row"><input id="pickup-reference-cep" :value="cep" @input="changeCep" @keydown.enter.prevent="lookup" inputmode="numeric" maxlength="9" placeholder="00000-000" autocomplete="postal-code" /><button type="button" @click="lookup" :disabled="busy"><LoaderCircle v-if="busy" :size="17" class="pickup-spin" />{{ busy?'Buscando…':'Buscar região' }}</button></div></div>
      <div><label for="pickup-search">Buscar ponto</label><div class="pickup-search-input"><Search :size="19" aria-hidden="true" /><input id="pickup-search" v-model="query" placeholder="Nome, bairro ou cidade" type="search" /></div></div>
    </div>
    <p v-if="message" role="status" class="pickup-finder-warning">{{ message }}</p>
    <div class="pickup-finder-region" aria-live="polite"><span v-if="region"><MapPin :size="16" />{{ [region.neighborhood, `${region.city}/${region.state}`].filter(Boolean).join(' · ') }}<small>Pontos da mesma região primeiro. A ordem não representa distância em quilômetros.</small></span><span v-else>Informe um CEP para priorizar pontos da sua região. Você também pode buscar diretamente.</span></div>
    <div v-if="selected" class="pickup-current"><Check :size="18" /><span>Preferência: <strong>{{ selected.name }}</strong></span><button type="button" @click="emit('update:modelValue',null)" aria-label="Limpar ponto de preferência"><X :size="17" /></button></div>
    <p v-if="loading" role="status">Carregando pontos de retirada…</p>
    <div v-else-if="error" class="pickup-empty" role="status"><p>Não foi possível carregar os pontos.</p><button type="button" @click="emit('retry')">Tentar novamente</button></div>
    <template v-else>
      <div v-if="visible.length" class="pickup-card-grid" role="radiogroup" aria-label="Ponto de preferência (opcional)">
        <article v-for="point in visible" :key="point.id" class="pickup-point-card" :class="{'pickup-point-selected':modelValue===point.id}">
          <label class="pickup-point-choice"><input type="radio" name="pickup-point" :value="point.id" :checked="modelValue===point.id" @change="emit('update:modelValue',point.id)" /><span class="pickup-point-check"><Check v-if="modelValue===point.id" :size="15" /></span><span class="pickup-point-content"><span class="pickup-point-name"><Store :size="19" /><strong>{{ point.name }}</strong></span><span v-if="regionLabel(regionPriority(point,region))" class="pickup-region-badge">{{ regionLabel(regionPriority(point,region)) }}</span><span class="pickup-point-address">{{ point.address || 'Endereço não informado. Consulte o ponto antes de ir.' }}</span><span class="pickup-choice-caption">{{ modelValue===point.id?'Ponto escolhido':'Escolher este ponto' }}</span></span></label>
          <div class="pickup-point-footer"><a v-if="pickupMapUrl(point)" :href="pickupMapUrl(point)" target="_blank" rel="noopener noreferrer">Ver no mapa <ArrowUpRight :size="16" /></a><span v-if="point.phone">{{ point.phone }}</span></div>
          <p v-if="modelValue===point.id && point.responsible" class="pickup-responsible">Responsável: {{ point.responsible }}</p>
        </article>
      </div>
      <div v-else class="pickup-empty" role="status"><p>{{ points.length?'Nenhum ponto encontrado para essa busca.':'Nenhum ponto de retirada disponível no momento.' }}</p><button v-if="query" type="button" @click="query=''">Limpar busca</button></div>
      <button v-if="filtered.length>6" type="button" class="pickup-show-more" @click="expanded=!expanded">{{ expanded?'Mostrar menos':`Ver todos os ${filtered.length} pontos` }}</button>
    </template>
    <p class="pickup-finder-note">A escolha é opcional. Apresente o QR de retirada disponível em <strong>Meus pedidos</strong>. Consulte a disponibilidade do kit antes de ir.</p>
  </section>
</template>
<style scoped>
.pickup-finder{background:#f8f9ff;border:1px solid #e0e4f2;border-radius:20px;padding:26px;min-width:0;font-family:Figtree,sans-serif;color:#172b4d}.pickup-finder-heading{display:flex;align-items:flex-start;gap:14px;margin-bottom:24px}.pickup-finder-icon{display:grid;place-items:center;flex-shrink:0;width:46px;height:46px;border-radius:14px;background:#eaeaff;color:#2820df}.pickup-finder h3{font-size:22px!important;line-height:1.25;margin:0 0 8px!important;font-weight:700}.pickup-finder-heading p{font-size:15px;line-height:1.6;margin:0!important;color:#627089}.pickup-finder-fields{display:grid;grid-template-columns:1fr 1fr;gap:18px}.pickup-finder-fields label{display:block;font-size:14px;font-weight:650;margin-bottom:8px}.pickup-finder input{min-width:0;width:100%;font:inherit;font-size:16px;color:#172b4d;background:#fff;border:1px solid #cbd4e5;border-radius:11px;padding:13px}.pickup-finder input:focus-visible,.pickup-finder button:focus-visible,.pickup-finder a:focus-visible{outline:3px solid #9590ff;outline-offset:3px}.pickup-cep-row{display:flex;gap:8px}.pickup-cep-row input{flex:1;width:80px}.pickup-cep-row button{display:flex;align-items:center;justify-content:center;gap:7px;background:#2820df;border:0;color:white;border-radius:11px;padding:10px 15px;font:600 14px Figtree,sans-serif;white-space:nowrap;cursor:pointer}.pickup-cep-row button:disabled{opacity:.65;cursor:wait}.pickup-search-input{position:relative}.pickup-search-input svg{position:absolute;left:13px;top:15px;color:#7c88a2}.pickup-search-input input{padding-left:41px}.pickup-finder-region{font-size:13px;line-height:1.5;color:#64728a;margin:14px 0 20px}.pickup-finder-region span{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.pickup-finder-region small{display:block;flex-basis:100%;font-size:12px}.pickup-finder-warning{font-size:14px;color:#986514;margin:12px 0!important}.pickup-current{display:flex;align-items:center;gap:8px;background:#eeedfF;border-radius:10px;padding:11px 13px;margin-bottom:16px;color:#3028b9;font-size:14px}.pickup-current button{margin-left:auto;border:0;background:transparent;padding:5px;color:inherit;cursor:pointer}.pickup-card-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.pickup-point-card{position:relative;min-width:0;background:white;border:1px solid #dce1ee;border-radius:16px;overflow:hidden;transition:border-color .2s,box-shadow .2s}.pickup-point-card:hover{border-color:#aaa5ef}.pickup-point-selected{border-color:#2820df;box-shadow:0 0 0 1px #2820df}.pickup-point-choice{display:flex;align-items:flex-start;gap:12px;padding:20px;cursor:pointer;position:relative;height:calc(100% - 49px)}.pickup-point-choice input{position:absolute;opacity:0;width:1px;height:1px;padding:0}.pickup-point-choice:has(input:focus-visible){outline:3px solid #9590ff;outline-offset:-4px;border-radius:14px}.pickup-point-check{width:22px;height:22px;border:1.5px solid #bec8dc;border-radius:50%;display:grid;place-items:center;flex-shrink:0;margin-top:1px}.pickup-point-selected .pickup-point-check{border-color:#2820df;background:#2820df;color:white}.pickup-point-content{display:flex;flex-direction:column;align-items:flex-start;gap:10px;min-width:0}.pickup-point-name{display:flex;align-items:center;gap:8px;font-size:17px;overflow-wrap:anywhere}.pickup-point-name svg{color:#2820df;flex-shrink:0}.pickup-region-badge{font-size:11px;font-weight:700;border-radius:20px;background:#eef5f1;color:#16724e;padding:4px 8px}.pickup-point-address{font-size:14px;line-height:1.65;color:#66748a;overflow-wrap:anywhere}.pickup-choice-caption{font-size:13px;font-weight:650;color:#2820df;margin-top:2px}.pickup-point-footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-top:1px solid #edf0f7;padding:12px 18px;font-size:12px;color:#66748a}.pickup-point-footer a{display:flex;align-items:center;gap:5px;color:#2820df;font-weight:650;text-decoration:none}.pickup-responsible{margin:0!important;padding:0 18px 12px;font-size:12px;color:#65738a}.pickup-finder-note{border-top:1px solid #e1e5f1;padding-top:18px;margin:22px 0 0!important;font-size:13px;line-height:1.7;color:#66748a}.pickup-empty{padding:22px;text-align:center;border:1px dashed #cbd4e5;border-radius:12px;color:#66748a;font-size:14px}.pickup-empty button,.pickup-show-more{background:white;color:#2820df;border:1px solid #d4d1f3;border-radius:9px;padding:10px 14px;font:600 13px Figtree,sans-serif;cursor:pointer}.pickup-show-more{display:block;margin:16px auto 0}.pickup-spin{animation:pickup-spin 1s linear infinite}@keyframes pickup-spin{to{transform:rotate(360deg)}}@media(max-width:900px){.pickup-finder-fields,.pickup-card-grid{grid-template-columns:1fr}}@media(max-width:480px){.pickup-finder{padding:18px 14px;border-radius:15px}.pickup-finder h3{font-size:20px!important}.pickup-finder-heading{gap:10px}.pickup-finder-icon{width:37px;height:37px;border-radius:11px}.pickup-finder-heading p{font-size:14px}.pickup-point-choice{padding:16px}.pickup-cep-row button{padding:9px 11px;font-size:13px}}
</style>
