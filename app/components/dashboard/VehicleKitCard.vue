<script setup lang="ts">
import { ShoppingBag, Tag } from '@lucide/vue';
import Skeleton from 'primevue/skeleton';
defineProps<{ image: string; type: string; code: string; plate: string; loading: boolean; canPurchaseTag: boolean }>();
const emit = defineEmits<{ purchase: [] }>();
</script>

<template>
<section class="it-kit-card rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div class="mb-6 flex h-10 items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#eaf0ff] text-[#1f46ee]">
                        <Tag :size="19" :stroke-width="1.9" aria-hidden="true" />
                    </div>
                    <h2 class="relative -top-3 leading-none text-xl! font-semibold text-[#172b4d] mb-0!">Kit de etiquetas</h2>
                </div>

                <div class="flex flex-col items-center text-center">
                    <div class="w-full max-w-[17rem] rounded-2xl border border-slate-300 bg-[#fafafa] p-6">
                        <p class="text-xs font-semibold tracking-[0.1em] text-[#172b4d]">IDENTIFICA TRÂNSITO</p>
                        <div class="my-5 flex justify-center">
                            <img :src="image" :alt="type" class="h-36 w-36 object-contain" />
                        </div>
                        <Skeleton v-if="loading" width="6rem" height="15px" class="mx-auto" />
                        <template v-else>
                            <p class="text-sm font-semibold text-[#172b4d] mb-1">{{ code || 'Indisponível' }}</p>
                            <p class="text-xs text-slate-500 mb-0!">{{ type }}</p>
                        </template>
                    </div>

                    <div class="mt-5 space-y-1">
                        <p class="text-sm text-slate-600 mb-0!">Veículo: <span class="font-semibold uppercase text-[#172b4d]">{{ plate || '—' }}</span></p>
                        <p class="text-sm text-slate-600 mb-0!">Código do kit: <span class="font-semibold text-[#172b4d]">{{ code || '—' }}</span></p>
                    </div>
                    <p :class="canPurchaseTag ? 'bg-[#eaf0ff] text-[#1f46ee]' : 'bg-slate-200 text-slate-600'" class="mt-4 inline-flex rounded-full px-3 py-1.5 text-xs font-semibold">
                        {{ canPurchaseTag ? 'Disponível para compra' : 'Etiqueta já adquirida para esta placa' }}
                    </p>
                    <button v-if="canPurchaseTag" type="button" class="mt-5 inline-flex items-center justify-center gap-2 rounded-lg bg-[#1f46ee] px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#1739d4]" @click="emit('purchase')">
                        <ShoppingBag :size="17" :stroke-width="2" aria-hidden="true" />
                        Comprar kit
                    </button>
                </div>
            </section>
</template>
