<script setup lang="ts">
const model = defineModel<string>({ default: '' });
defineProps<{ disabled?: boolean }>();
</script>
<template>
    <section class="rounded-2xl border border-[#dce2ff] bg-[#f7f8ff] p-5 md:p-6 space-y-4">
        <h2 class="flex items-center gap-3 text-lg! font-semibold text-[#172b4d]"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#e9edff] text-it-primary"><i class="pi pi-percentage" aria-hidden="true"></i></span>Repasse do PDV</h2>
        <p class="text-[#52667f]">Defina uma vez o percentual deste ponto de venda. Todos os usuários administradores vinculados a ele usam a mesma regra.</p>
        <div class="max-w-sm">
            <label for="commission_percentage" class="block mb-2 font-semibold text-[#172b4d]">Percentual de repasse (%)</label>
            <div class="relative"><input id="commission_percentage" v-model="model" type="number" min="0" max="100" step="0.01" inputmode="decimal" placeholder="Informe o percentual" :disabled="disabled" aria-describedby="commission_help" class="w-full rounded-xl border border-[#ccd5e5] bg-white py-3 pl-4 pr-12 text-lg focus:outline-none focus:ring-2 focus:ring-it-primary" /><span aria-hidden="true" class="absolute right-4 top-3 text-[#52667f]">%</span></div>
        </div>
        <p id="commission_help" class="text-sm text-[#52667f]">De 0% a 100%, com até duas casas decimais. Em branco: repasse não configurado. O percentual é aplicado às vendas aprovadas atribuídas aos usuários deste PDV; a retirada em outro ponto não altera essa atribuição. Alterações recalculam os relatórios dos períodos consultados.</p>
    </section>
</template>
