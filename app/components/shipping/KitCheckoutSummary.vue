<script setup lang="ts">
import { X } from '@lucide/vue';
const props=defineProps<{vehicles:Array<{id:number;plate:string;name?:string;tag_color:string;usage_profile_label:string}>}>();
const emit=defineEmits<{remove:[id:number]}>();
const selectedVehicles=computed(()=>props.vehicles);
const subtotalKits=computed(()=>selectedVehicles.value.length*29.90);
const config=useRuntimeConfig();
const getTagImage=(value:string)=>{const map:Record<string,string>={branca:'branco',branco:'branco',amarela:'amarelo',amarelo:'amarelo',azul:'azul',verde:'verde',vermelha:'vermelho',vermelho:'vermelho',laranja:'azul'};return `${config.app.baseURL}images/dashboard/etiquetas-v2/${map[String(value).toLowerCase()]||'azul'}.svg`.replace(/\/+/g,'/');};
</script>
<template><div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                                    <h3 class="text-lg! color-it-primary font-semibold mb-3">Resumo dos Kits de Etiquetas</h3><p class="mb-4 text-sm text-[#52667f]">Cada placa pode comprar 1 kit. Para comprar outro kit, cadastre outro veículo. <NuxtLink to="/dashboard/veiculos/novo" class="font-semibold text-it-primary underline">Cadastrar outro veículo</NuxtLink></p>
                                    <div class="space-y-3">
                                        <div
                                            v-for="vehicle in selectedVehicles"
                                            :key="vehicle.id"
                                            class="flex items-start justify-between gap-3 text-sm text-gray-700 p-3 bg-gray-50 rounded-lg"
                                        >
                                            <img :src="getTagImage(vehicle.tag_color)" :alt="`Etiqueta ${vehicle.tag_color}`" class="checkout-summary-tag" />
                                            <div class="flex-1 space-y-1 min-w-0">
                                                <p class="font-bold truncate">{{ vehicle.name }}</p>
                                                <p class="text-gray-500 capitalize">Placa: {{ vehicle.plate?.toUpperCase() }} • {{ vehicle.usage_profile_label }} • Etiqueta {{ vehicle.tag_color }}</p>
                                                <div class="flex items-center gap-4 justify-between">
                                                    <p class="mt-2 font-semibold text-[#172b4d]">1 kit por placa</p>
                                                    
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-2 shrink-0">
                                                <button
                                                    type="button"
                                                    class="text-red-400 hover:text-red-600 transition"
                                                    :aria-label="`Remover veículo ${vehicle.plate}`"
                                                    v-tooltip.top="'Remover item'"
                                                    @click="emit('remove',vehicle.id)"
                                                >
                                                    <X :size="18" />
                                                </button>
                                                <span class="font-semibold whitespace-nowrap">R$ {{ (29.90).toFixed(2) }}</span>
                                                <p>1 kit</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-200 mt-4 pt-3 text-sm font-semibold text-gray-900">
                                        Subtotal ({{ selectedVehicles.length }} kit{{ selectedVehicles.length > 1 ? 's' : '' }}): R$ {{ subtotalKits.toFixed(2) }}
                                    </div>
                                </div></template>

<style scoped>
.checkout-summary-tag{width:3.35rem;height:3.35rem;flex:0 0 auto;object-fit:contain;border:1px solid #e1e7f0;border-radius:.7rem;background:#fff;padding:.3rem}
</style>
