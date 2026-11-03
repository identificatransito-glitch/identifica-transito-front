<script setup lang="ts">
import FormInputText from '~/components/forms/InputText.vue';
const props = withDefaults(defineProps<{ modelValue?: string; disabled?: boolean }>(), { modelValue: '', disabled: false });
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void; (event: 'busy', value: boolean): void; (event: 'validity', value: boolean): void }>();
const zip = ref(''), street = ref(''), number = ref(''), complement = ref(''), neighborhood = ref(''), city = ref(''), state = ref('');
const searching = ref(false), message = ref(''), failed = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined, controller: AbortController | undefined, lastZip = '', emitted = '';
const digits = computed(() => zip.value.replace(/\D/g, ''));
const address = computed(() => [street.value.trim(), number.value.trim() ? `nº ${number.value.trim()}` : '', complement.value.trim() ? `compl. ${complement.value.trim()}` : '', neighborhood.value.trim() ? `bairro ${neighborhood.value.trim()}` : '', [city.value.trim(), state.value.trim().toUpperCase()].filter(Boolean).join('/'), digits.value.length === 8 ? `CEP ${digits.value.slice(0, 5)}-${digits.value.slice(5)}` : ''].filter(Boolean).join(' — '));
const valid = computed(() => (!digits.value.length || digits.value.length === 8) && address.value.length <= 255);
watch(() => props.modelValue, (value) => {
  if (value === emitted) return;
  const parts = (value || '').split(' — ');
  street.value = parts.shift() || ''; number.value = ''; complement.value = ''; neighborhood.value = ''; city.value = ''; state.value = ''; zip.value = '';
  for (const part of parts) {
    if (part.startsWith('nº ')) number.value = part.slice(3);
    else if (part.startsWith('compl. ')) complement.value = part.slice(7);
    else if (part.startsWith('bairro ')) neighborhood.value = part.slice(7);
    else if (/^CEP \d{5}-\d{3}$/.test(part)) zip.value = part.slice(4);
    else if (/\/[A-Z]{2}$/.test(part)) { city.value = part.slice(0, -3); state.value = part.slice(-2); }
    else street.value += ` — ${part}`;
  }
  // Existing saved addresses do not trigger a new lookup or overwrite manual edits.
  lastZip = zip.value.replace(/\D/g, '');
}, { immediate: true });
watch(address, (value) => { emitted = value; emit('update:modelValue', value); });
watch(valid, value => emit('validity', value), { immediate: true });
watch(digits, (value) => {
  clearTimeout(timer); controller?.abort(); searching.value = false; emit('busy', false); message.value = ''; failed.value = false;
  if (value.length === 8 && value !== lastZip) { searching.value = true; emit('busy', true); timer = setTimeout(lookup, 400); }
});
async function lookup() {
  clearTimeout(timer);
  const cep = digits.value;
  if (cep.length !== 8 || props.disabled || cep === lastZip) return;
  controller?.abort(); const current = new AbortController(); controller = current;
  searching.value = true; emit('busy', true); failed.value = false; message.value = 'Buscando endereço…';
  const before = { street: street.value, neighborhood: neighborhood.value, city: city.value, state: state.value };
  const timeout = setTimeout(() => current.abort(), 8000);
  try {
    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`, { signal: current.signal, referrerPolicy: 'no-referrer', credentials: 'omit' });
    if (!response.ok) throw new Error('unavailable');
    const data = await response.json();
    if (current !== controller || digits.value !== cep) return;
    if (data.erro || !data.localidade || !/^[A-Z]{2}$/.test(data.uf || '')) throw new Error('notfound');
    if (street.value === before.street) street.value = data.logradouro || '';
    if (neighborhood.value === before.neighborhood) neighborhood.value = data.bairro || '';
    if (city.value === before.city) city.value = data.localidade;
    if (state.value === before.state) state.value = data.uf;
    lastZip = cep;
    message.value = data.logradouro ? 'Endereço encontrado. Informe o número e confira os dados.' : 'CEP encontrado. Informe a rua e o número e confira os dados.';
  } catch (error: any) {
    if (current !== controller || digits.value !== cep) return;
    failed.value = true; message.value = error.message === 'notfound' ? 'CEP não encontrado. Confira o CEP ou preencha o endereço manualmente.' : 'Não foi possível buscar o CEP. Você pode preencher o endereço manualmente.';
  } finally {
    clearTimeout(timeout);
    if (current === controller) { searching.value = false; emit('busy', false); }
  }
}
onBeforeUnmount(() => { clearTimeout(timer); controller?.abort(); });
</script>
<template>
  <fieldset class="pdv-address space-y-5" :disabled="disabled">
    <legend class="mb-4 text-base font-semibold text-[#172b4d]">Endereço do ponto</legend>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <FormInputText wrapper-class="w-full min-w-0" input-class="w-full min-w-0" v-model="zip" id="pdv-cep" label="CEP" placeholder="00000-000" mask="99999-999" :auto-clear="false" autocomplete="postal-code" showIcon icon="pi pi-map-marker" :disabled="disabled" @blur="lookup" />
      <div class="flex items-center"><p class="text-sm text-slate-500 m-0!">Digite o CEP para preencher o endereço. Você pode ajustar todos os campos.</p></div>
    </div>
    <p v-if="message" role="status" :class="failed ? 'text-amber-700' : 'text-[#1f46ee]'" class="text-sm m-0!">{{ message }}</p>
    <p v-if="digits.length && digits.length !== 8" class="text-sm text-amber-700 m-0!">O CEP deve ter 8 dígitos.</p>
    <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_9rem] gap-6">
      <FormInputText wrapper-class="w-full min-w-0" input-class="w-full min-w-0" v-model="street" id="pdv-street" label="Rua / Avenida" placeholder="Nome da rua ou avenida" autocomplete="address-line1" showIcon icon="pi pi-map-marker" :disabled="disabled" />
      <FormInputText wrapper-class="w-full min-w-0" input-class="w-full min-w-0" v-model="number" id="pdv-number" label="Número" placeholder="Ex: 47 ou s/n" :disabled="disabled" />
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <FormInputText wrapper-class="w-full min-w-0" input-class="w-full min-w-0" v-model="neighborhood" id="pdv-neighborhood" label="Bairro" placeholder="Bairro do ponto" :disabled="disabled" />
      <FormInputText wrapper-class="w-full min-w-0" input-class="w-full min-w-0" v-model="complement" id="pdv-complement" label="Complemento" placeholder="Opcional: loja, sala…" autocomplete="address-line2" :disabled="disabled" />
    </div>
    <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_9rem] gap-6">
      <FormInputText wrapper-class="w-full min-w-0" input-class="w-full min-w-0" v-model="city" id="pdv-city" label="Cidade" placeholder="Cidade" autocomplete="address-level2" :disabled="disabled" />
      <FormInputText wrapper-class="w-full min-w-0" input-class="w-full min-w-0" v-model="state" id="pdv-state" label="Estado (UF)" placeholder="Ex: SP" autocomplete="address-level1" :disabled="disabled" />
    </div>
    <p v-if="address.length > 255" role="alert" class="text-sm text-red-700">O endereço completo deve ter até 255 caracteres. Abrevie o complemento ou o logradouro.</p>
  </fieldset>
</template>

<style scoped>
.pdv-address{min-width:0;max-width:100%}.pdv-address .grid>*{min-width:0}.pdv-address :deep(input){box-sizing:border-box;width:100%;max-width:100%;min-width:0}
</style>
