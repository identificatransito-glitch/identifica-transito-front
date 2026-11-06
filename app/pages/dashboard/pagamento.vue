<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue';
import KitCheckoutSummary from '~/components/shipping/KitCheckoutSummary.vue';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import Button from '~/components/forms/Button.vue';
import InputText from '~/components/forms/InputText.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import PickupPointSelector from '~/components/pickup/PickupPointSelector.vue';
import { type PickupRegion, zipDigits } from '~/utils/pickupPoints';
import CreditCardPreview from '~/components/dashboard/CreditCardPreview.vue';
import Stepper from 'primevue/stepper';
import StepItem from 'primevue/stepitem';
import Step from 'primevue/step';
import StepPanel from 'primevue/steppanel';

definePageMeta({
    layout: 'dashboard'
});

const config = useRuntimeConfig();
const assetWithBase = (path: string) => {
    if (path.startsWith('data:') || path.startsWith('http')) return path;
    return `${config.app.baseURL}${path}`.replace(/\/+/g, '/').replace(':/', '://');
};

const getTagImage = (tagColor: string) => {
    const color = String(tagColor || '').toLowerCase();
    const colorMap: Record<string, string> = {
        branco: 'branco',
        branca: 'branco',
        amarela: 'amarelo',
        amarelo: 'amarelo',
        azul: 'azul',
        verde: 'verde',
        vermelha: 'vermelho',
        vermelho: 'vermelho',
        laranja: 'azul'
    };

    return assetWithBase(`/images/dashboard/etiquetas-v2/${colorMap[color] || 'azul'}.svg`);
};

useHead({
    title: 'Pagamento - Identifica Trânsito',
    meta: [
        { name: 'description', content: 'Revise sua compra, escolha entrega e pague suas etiquetas.' }
    ]
});

interface Vehicle {
    id: number;
    plate: string;
    name: string;
    tag_price: string;
    tag_color: string;
    usage_profile_label: string;
}

interface PixResponse {
    message: string;
    payment_id: number;
    status: string;
    status_detail: string;
    status_label: string;
    qr_code: string;
    qr_code_base64: string;
    boleto_url: string;
    purchases: any[];
}

interface BoletoResponse {
    message: string;
    payment_id: number;
    status: string;
    status_label: string;
    barcode: string | null;
    boleto_url: string;
    purchases: any[];
}

interface PointOfSaleApiItem {
    id: number;
    name: string;
    address: string | null;
    map_link: string | null;
    responsible: string | null;
    phone?: string | null;
}

interface PickupPointOption {
    label: string;
    value: number;
    endereco: string;
    mapLink: string;
    responsible: string;
    phone?: string | null;
}

const toast = useToast();

// Veículos selecionados vindos da página de etiquetas
const selectedVehicles = useState<Vehicle[]>('selectedVehicles', () => []);

// Um kit por veículo selecionado. Não há quantidade editável.
const getTotalUnits = () => selectedVehicles.value.length;

const removeVehicle = (id: number) => {
    selectedVehicles.value = selectedVehicles.value.filter(v => v.id !== id);
    couponData.value = null;
    couponError.value = '';
    if (selectedVehicles.value.length === 0) {
        navigateTo('/dashboard/etiquetas');
    }
};

onMounted(() => {
    if (selectedVehicles.value.length === 0) {
        navigateTo('/dashboard/etiquetas');
    }

    fetchShippingSettings();
    fetchPickupPoints();
    fetchSavedPickupAddress();
});

const shippingSettings=ref({ enabled:false, fixed_amount:0 });
const shippingLoading=ref(true),shippingError=ref(false);
const fetchShippingSettings=async()=>{
 shippingLoading.value=true;shippingError.value=false;
 try{const {$api}=useNuxtApp();const response:any=await $api('/shipping-settings');const data=response?.data??response;
  const amount=Number(data.fixed_amount);if(typeof data.enabled!=='boolean'||!Number.isFinite(amount)||amount<0)throw new Error('Invalid shipping settings');
  shippingSettings.value={enabled:data.enabled,fixed_amount:amount};
 }catch{shippingError.value=true;}finally{shippingLoading.value=false;}
};
const frete=computed(()=>entrega.value==='casa'&&shippingSettings.value.enabled?shippingSettings.value.fixed_amount:0);
const subtotalKits = computed(() =>
    selectedVehicles.value.reduce((sum, v) => sum + 29.90, 0)
);
const total = computed(() => subtotalKits.value + frete.value);

// Cupom de desconto
const couponCode = ref('');
const couponLoading = ref(false);
const couponError = ref('');
const couponData = ref<{
    coupon: { code: string; type: number; value: number };
    subtotal: number;
    discount: number;
    total: number;
} | null>(null);

const displayTotal = computed(() => couponData.value ? couponData.value.total + frete.value : total.value);
const isFree = computed(() => displayTotal.value === 0);

const vehiclesPayload = computed(() =>
    selectedVehicles.value.map(v => ({ id: v.id }))
);

const applyCoupon = async () => {
    const code = couponCode.value.trim();
    if (!code) return;
    couponLoading.value = true;
    couponError.value = '';
    couponData.value = null;
    try {
        const { $api } = useNuxtApp();
        const res = await $api('/tag-purchases/coupon-preview', {
            method: 'POST',
            body: { coupon_discount: code, vehicles: vehiclesPayload.value },
        }) as any;
        couponData.value = res?.data ?? res;
    } catch (error: any) {
        couponError.value = error?.data?.message || 'Cupom de desconto inválido ou expirado.';
    } finally {
        couponLoading.value = false;
    }
};

const removeCoupon = () => {
    couponCode.value = '';
    couponData.value = null;
    couponError.value = '';
};

// Opções de pagamento
// Temporarily hidden; keep the card integration for future reactivation.
const CREDIT_CARD_ENABLED = false;
const paymentMethod = ref<'credito' | 'pix' | 'boleto'>('pix');

// Opção de entrega
const entrega = ref<'casa' | 'ponto'>('casa');

// Dados de coleta em ponto
const pontoColeta = ref<number | null>(null);
const pontosColetaOptions = ref<PickupPointOption[]>([]);
const pickupLoading = ref(false), pickupError = ref(false);
const savedPickupAddress = ref<PickupRegion | null>(null);
const pickupPoints = computed(() => pontosColetaOptions.value.map(point => ({id:point.value,name:point.label,address:point.endereco,map_link:point.mapLink,responsible:point.responsible,phone:point.phone})));
const pickupReference = computed<PickupRegion | null>(() => zipDigits(endereco.value.cep).length===8
    ? {zip:zipDigits(endereco.value.cep),city:endereco.value.cidade,state:endereco.value.estado,neighborhood:endereco.value.bairro}
    : savedPickupAddress.value);
const fetchSavedPickupAddress = async () => {
    try {
        const { $api } = useNuxtApp();
        const response:any = await $api('/orders?perpage=10&page=1');
        const payload=response?.data?.data ? response.data : response;
        const orders=Array.isArray(payload)?payload:payload?.data || [];
        const delivery=orders.find((order:any)=>zipDigits(order.delivery?.zip_code || '').length===8)?.delivery;
        if (delivery) savedPickupAddress.value={zip:zipDigits(delivery.zip_code),city:delivery.city || '',state:(delivery.state || '').toUpperCase(),neighborhood:delivery.neighborhood || ''};
    } catch { /* Without a saved address the visitor can enter a reference CEP. */ }
};

const fetchPickupPoints = async () => {
    pickupLoading.value=true; pickupError.value=false;
    try {
        const { $api } = useNuxtApp();
        const response = await $api('/points-of-sale?perpage=999') as any;
        const data = response?.data || response;
        const list = Array.isArray(data) ? data : data?.data || [];

        pontosColetaOptions.value = (list as PointOfSaleApiItem[]).filter((point: any) => ![false, 0, '0', 'false'].includes(point.active)).map((point) => ({
            label: point.name,
            value: point.id,
            endereco: point.address || '',
            mapLink: point.map_link || '',
            responsible: point.responsible || '',
            phone: point.phone || null,
        }));
    } catch (error) {
        console.error('Erro ao carregar pontos de retirada:', error);
        pontosColetaOptions.value = [];
        pickupError.value=true;
    } finally { pickupLoading.value=false; }
};

// Dados de cartão de crédito
/*
cpf apro => 12345678909 Nome aprovado => APRO
cpf rec => 12345678909 Nome recusado => OTHE
*/
const cartao = ref({ numero: '', nome: '', validade: '', cvc: '', cpf: '' });

const isCreditCardValid = computed(() => {
    const { numero, nome, validade, cvc, cpf } = cartao.value;
    return !![
        numero.replace(/\s/g, ''),
        nome,
        validade,
        cvc,
        cpf.replace(/\D/g, ''),
    ].every(v => v?.trim().length > 0);
});

const isPaymentButtonDisabled = computed(() => {
    if (shippingLoading.value || shippingError.value) return true;
    if (!isDeliveryValid.value) return true;
    if (isFree.value) return false;
    if (paymentMethod.value === 'credito') return !isCreditCardValid.value;
    if (paymentMethod.value === 'boleto') return !isBoletoValid.value;
    return false;
});

const paymentButtonTooltip = computed(() => {
    if (shippingLoading.value || shippingError.value) return 'Aguarde a confirmação do frete ou tente carregar novamente.';
    if (!isDeliveryValid.value) return 'Preencha a forma de entrega ou retirada no passo 1 para continuar.';
    if (isFree.value) return undefined;
    if (paymentMethod.value === 'credito' && !isCreditCardValid.value) return 'Preencha todos os dados do cartão de crédito, incluindo CPF.';
    if (paymentMethod.value === 'boleto' && !isBoletoValid.value) return 'Preencha o endereço do pagador para gerar o boleto.';
    return undefined;
});

// Dados de endereço
const endereco = ref({ cep: '', rua: '', numero: '', complemento: '', bairro: '', cidade: '', estado: '' });

const estados = [
    { label: 'AC - Acre', value: 'AC' }, { label: 'AL - Alagoas', value: 'AL' },
    { label: 'AP - Amapá', value: 'AP' }, { label: 'AM - Amazonas', value: 'AM' },
    { label: 'BA - Bahia', value: 'BA' }, { label: 'CE - Ceará', value: 'CE' },
    { label: 'DF - Distrito Federal', value: 'DF' }, { label: 'ES - Espírito Santo', value: 'ES' },
    { label: 'GO - Goiás', value: 'GO' }, { label: 'MA - Maranhão', value: 'MA' },
    { label: 'MT - Mato Grosso', value: 'MT' }, { label: 'MS - Mato Grosso do Sul', value: 'MS' },
    { label: 'MG - Minas Gerais', value: 'MG' }, { label: 'PA - Pará', value: 'PA' },
    { label: 'PB - Paraíba', value: 'PB' }, { label: 'PR - Paraná', value: 'PR' },
    { label: 'PE - Pernambuco', value: 'PE' }, { label: 'PI - Piauí', value: 'PI' },
    { label: 'RJ - Rio de Janeiro', value: 'RJ' }, { label: 'RN - Rio Grande do Norte', value: 'RN' },
    { label: 'RS - Rio Grande do Sul', value: 'RS' }, { label: 'RO - Rondônia', value: 'RO' },
    { label: 'RR - Roraima', value: 'RR' }, { label: 'SC - Santa Catarina', value: 'SC' },
    { label: 'SP - São Paulo', value: 'SP' }, { label: 'SE - Sergipe', value: 'SE' },
    { label: 'TO - Tocantins', value: 'TO' },
];

const loadingCep = ref(false);
const cepError = ref('');

const buscarCEP = async (cep: string) => {
    const cepLimpo = cep.replace(/\D/g, '');
    if (cepLimpo.length !== 8) return;
    loadingCep.value = true;
    cepError.value = '';
    try {
        const response = await fetch(`https://viacep.com.br/ws/${cepLimpo}/json/`);
        const data = await response.json();
        if (data.erro) { cepError.value = 'CEP não encontrado. Você pode preencher o endereço manualmente.'; return; }
        endereco.value.rua = data.logradouro;
        endereco.value.bairro = data.bairro;
        endereco.value.cidade = data.localidade;
        endereco.value.estado = data.uf;
    } catch (error) {
        cepError.value = 'Não foi possível consultar o CEP. Preencha o endereço manualmente.';
        console.error(error);
    } finally {
        loadingCep.value = false;
    }
};

const handleCepChange = (value: string) => {
    endereco.value.cep = value;
    cepError.value = '';
    buscarCEP(value);
};

const deliveryValidationShown = ref(false);
const deliveryFieldInvalid = (field: 'rua' | 'numero' | 'bairro' | 'cidade' | 'estado') =>
    deliveryValidationShown.value && entrega.value === 'casa' && !String(endereco.value[field] ?? '').trim();
const requiredDeliveryFields = { rua: 'Logradouro', numero: 'Número', bairro: 'Bairro', cidade: 'Cidade', estado: 'Estado' } as const;
const missingDeliveryFields = computed(() => entrega.value === 'casa'
    ? Object.entries(requiredDeliveryFields).filter(([key]) => !String(endereco.value[key as keyof typeof requiredDeliveryFields] || '').trim()).map(([, label]) => label)
    : []);
const isDeliveryValid = computed(() => entrega.value === 'casa'
    ? missingDeliveryFields.value.length === 0
    : pontosColetaOptions.value.length > 0);
const validateDelivery = () => {
    deliveryValidationShown.value = true;
    return isDeliveryValid.value;
};
const continueDelivery = (activateCallback: (step: number) => void) => {
    if (validateDelivery()) activateCallback(2);
};

const isBoletoValid = computed(() => {
    const { cep, rua, numero, bairro, cidade, estado } = boletoEndereco.value;
    return !![cep, rua, numero, bairro, cidade, estado].every(v => v?.trim());
});

// --- Boleto ---
const boletoData = ref<BoletoResponse | null>(null);
const boletoUsarEnderecoEntrega = ref(false);
const boletoEndereco = ref({ cep: '', rua: '', numero: '', bairro: '', cidade: '', estado: '' });

watch(boletoUsarEnderecoEntrega, (val) => {
    if (val) {
        const { cep, rua, numero, bairro, cidade, estado } = endereco.value;
        boletoEndereco.value = { cep, rua, numero, bairro, cidade, estado };
    }
});

const buscarCEPBoleto = async (cep: string) => {
    const cepLimpo = cep.replace(/\D/g, '');
    if (cepLimpo.length !== 8) return;
    loadingCep.value = true;
    try {
        const response = await fetch(`https://viacep.com.br/ws/${cepLimpo}/json/`);
        const data = await response.json();
        if (data.erro) return;
        boletoEndereco.value.rua = data.logradouro;
        boletoEndereco.value.bairro = data.bairro;
        boletoEndereco.value.cidade = data.localidade;
        boletoEndereco.value.estado = data.uf;
    } catch (error) {
        console.error(error);
    } finally {
        loadingCep.value = false;
    }
};

const handleBoletoCepChange = (value: string) => {
    boletoEndereco.value.cep = value;
    buscarCEPBoleto(value);
};

// --- PIX ---
const pixData = ref<PixResponse | null>(null);
const loadingPayment = ref(false);
const paymentSuccess = ref(false);

// Statuses do Mercado Pago
// pending / in_process / authorized → continua aguardando
// approved                          → sucesso
// rejected / cancelled / refunded / charged_back / in_mediation → falha
const PENDING_STATUSES = ['pending', 'in_process', 'authorized'];
const SUCCESS_STATUSES = ['approved'];

const timerSeconds = ref(600);
const timerDisplay = computed(() => {
    const m = Math.floor(timerSeconds.value / 60);
    const s = timerSeconds.value % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

let timerInterval: ReturnType<typeof setInterval> | null = null;
let pollInterval: ReturnType<typeof setInterval> | null = null;

const stopPolling = () => {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
};

const checkPaymentStatus = async () => {
    if (!pixData.value?.payment_id) return;
    try {
        const { $api } = useNuxtApp();
        const status = await $api(`/tag-purchases/${pixData.value.payment_id}/status`) as {
            payment_id: number;
            status: string;
            status_detail: string;
            status_label: string;
        };

        if (SUCCESS_STATUSES.includes(status.status)) {
            stopPolling();
            if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
            paymentSuccess.value = true;
        } else if (status.status === 'expired') {
            stopPolling();
            if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
            timerSeconds.value = 600;
            pixData.value = null;
            toast.add({
                severity: 'warn',
                summary: 'PIX expirado',
                detail: 'O tempo para pagamento expirou. Gere um novo QR Code para tentar novamente.',
                life: 6000,
            });
        } else if (status.status === 'failed') {
            stopPolling();
            if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
            timerSeconds.value = 600;
            pixData.value = null;
            toast.add({
                severity: 'error',
                summary: 'Falha no pagamento',
                detail: 'Ocorreu uma falha ao processar o pagamento. Tente gerar um novo QR Code.',
                life: 6000,
            });
        } else if (!PENDING_STATUSES.includes(status.status)) {
            // rejected / cancelled / refunded / charged_back / in_mediation
            stopPolling();
            toast.add({
                severity: 'error',
                summary: 'Pagamento recusado',
                detail: `Status: ${status.status_label}. Tente novamente ou escolha outra forma de pagamento.`,
                life: 8000,
            });
        }
    } catch (error) {
        console.error('Erro ao verificar status do pagamento:', error);
    }
};

const startPolling = () => {
    stopPolling();
    pollInterval = setInterval(checkPaymentStatus, 15000);
};

const startTimer = () => {
    if (timerInterval) clearInterval(timerInterval);
    timerSeconds.value = 600;
    timerInterval = setInterval(() => {
        timerSeconds.value--;
        if (timerSeconds.value <= 0) {
            clearInterval(timerInterval!);
            timerInterval = null;
            stopPolling();
            pixData.value = null;
        }
    }, 1000);
};

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    stopPolling();
});

const copied = ref(false);
const copyPixCode = async () => {
    if (!pixData.value?.qr_code) return;
    await navigator.clipboard.writeText(pixData.value.qr_code);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
};

const buildDeliveryPayload = () => {
    if (entrega.value === 'casa') {
        return {
            is_delivery: true,
            delivery_address: {
                zip_code: endereco.value.cep,
                street: endereco.value.rua,
                number: endereco.value.numero,
                complement: endereco.value.complemento,
                neighborhood: endereco.value.bairro,
                city: endereco.value.cidade,
                state: endereco.value.estado,
            },
        };
    }

    const ponto = pontosColetaOptions.value.find(p => p.value === pontoColeta.value) || pontosColetaOptions.value[0];
    return {
        is_delivery: false,
        pickup_point: {
            id: ponto?.value ?? null,
            label: ponto?.label ?? null,
        },
    };
};

const loadMercadoPago = (): Promise<void> => {
    return new Promise((resolve, reject) => {
        if ((window as any).MercadoPago) { resolve(); return; }
        const script = document.createElement('script');
        script.src = 'https://sdk.mercadopago.com/js/v2';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Falha ao carregar SDK do MercadoPago'));
        document.head.appendChild(script);
    });
};

const handlePagar = async () => {
    if (!validateDelivery()) {
        toast.add({ severity: 'warn', summary: 'Confira a entrega', detail: entrega.value === 'casa' ? `Preencha: ${missingDeliveryFields.value.join(', ')}.` : 'Selecione um ponto de retirada disponível.', life: 5000 });
        return;
    }
    if(shippingLoading.value||shippingError.value){toast.add({severity:'warn',summary:'Frete indisponível',detail:'Tente carregar o frete novamente antes de pagar.',life:5000});return;}
    if (isFree.value) {
        loadingPayment.value = true;
        try {
            const { $api } = useNuxtApp();
            await $api('/tag-purchases', {
                method: 'POST',
                body: {
                    payment_method: 'free',
                    coupon_discount: couponData.value?.coupon?.code || null,
                    shipping_price: frete.value,
                    vehicles: vehiclesPayload.value,
                    ...buildDeliveryPayload(),
                },
            });
            paymentSuccess.value = true;
        } catch (error: any) {
            const errorMsg = error?.data?.message || error?.message || 'Erro ao confirmar pedido.';
            toast.add({ severity: 'error', summary: 'Erro ao confirmar', detail: errorMsg, life: 5000 });
        } finally {
            loadingPayment.value = false;
        }
        return;
    }

    if (paymentMethod.value === 'credito') {
        if (!CREDIT_CARD_ENABLED) {
            paymentMethod.value='pix';
            toast.add({severity:'info',summary:'Escolha Pix ou boleto',detail:'Cartão de crédito indisponível no momento.',life:4000});
            return;
        }
        loadingPayment.value = true;
        try {
            const config = useRuntimeConfig();
            await loadMercadoPago();
            const mp = new (window as any).MercadoPago(config.public.mercadoPagoPublicKey, { locale: 'pt-BR' });

            // Identify card brand and issuer from BIN
            const bin = cartao.value.numero.replace(/\s/g, '').slice(0, 6);
            const { results } = await mp.getPaymentMethods({ bin });
            if (!results?.length) throw new Error('Cartão não identificado. Verifique o número e tente novamente.');
            const paymentMethodId: string = results[0].id;
            const issuerId: string | undefined = results[0].issuer?.id;

            // Parse expiry MM/YY
            const [month, year] = cartao.value.validade.split('/');

            // Tokenize card
            const tokenResponse = await mp.createCardToken({
                cardNumber: cartao.value.numero.replace(/\s/g, ''),
                cardholderName: cartao.value.nome,
                cardExpirationMonth: month,
                cardExpirationYear: `20${year}`,
                securityCode: cartao.value.cvc,
                identificationType: 'CPF',
                identificationNumber: cartao.value.cpf.replace(/\D/g, ''),
            });

            if (!tokenResponse?.id) throw new Error('Não foi possível tokenizar o cartão. Verifique os dados e tente novamente.');

            const { $api } = useNuxtApp();
            const response = await $api('/tag-purchases', {
                method: 'POST',
                body: {
                    payment_method: 'credit_card',
                    card_token: tokenResponse.id,
                    installments: 1,
                    payment_method_id: paymentMethodId,
                    issuer_id: issuerId ?? null,
                    coupon_discount: couponData.value?.coupon?.code || null,
                    shipping_price: frete.value,
                    vehicles: vehiclesPayload.value,
                    ...buildDeliveryPayload(),
                },
            }) as { status: string; status_label?: string; status_detail?: string };

            if (response.status === 'approved') {
                paymentSuccess.value = true;
            } else if (['pending', 'in_process', 'authorized'].includes(response.status)) {
                toast.add({
                    severity: 'info',
                    summary: 'Pagamento em análise',
                    detail: 'Seu pagamento está sendo processado. Você será notificado em breve.',
                    life: 8000,
                });
            } else {
                toast.add({
                    severity: 'error',
                    summary: 'Pagamento recusado',
                    detail: response.status_label || 'O pagamento foi recusado. Verifique os dados do cartão ou tente outra forma de pagamento.',
                    life: 7000,
                });
            }
        } catch (error: any) {
            const errorMsg = error?.data?.message || error?.message || 'Erro ao processar pagamento com cartão.';
            toast.add({ severity: 'error', summary: 'Erro no pagamento', detail: errorMsg, life: 5000 });
        } finally {
            loadingPayment.value = false;
        }
        return;
    }

    if (paymentMethod.value === 'boleto') {
        loadingPayment.value = true;
        try {
            const { $api } = useNuxtApp();
            const addr = boletoEndereco.value;
            const response = await $api('/tag-purchases', {
                method: 'POST',
                body: {
                    payment_method: 'boleto',
                    coupon_discount: couponData.value?.coupon?.code || null,
                    shipping_price: frete.value,
                    vehicles: vehiclesPayload.value,
                    payer_zip_code: addr.cep,
                    payer_street: addr.rua,
                    payer_number: addr.numero,
                    payer_neighborhood: addr.bairro,
                    payer_city: addr.cidade,
                    payer_state: addr.estado,
                    ...buildDeliveryPayload(),
                },
            });
            boletoData.value = response as BoletoResponse;
        } catch (error: any) {
            const errorMsg = error?.data?.message || error?.message || 'Erro ao gerar boleto.';
            toast.add({ severity: 'error', summary: 'Erro ao gerar boleto', detail: errorMsg, life: 5000 });
        } finally {
            loadingPayment.value = false;
        }
        return;
    }

    if (paymentMethod.value === 'pix') {
        pixData.value = null;
        loadingPayment.value = true;
        try {
            const { $api } = useNuxtApp();
            const response = await $api('/tag-purchases', {
                method: 'POST',
                body: {
                    payment_method: 'pix',
                    coupon_discount: couponData.value?.coupon?.code || null,
                    shipping_price: frete.value,
                    vehicles: vehiclesPayload.value,
                    ...buildDeliveryPayload(),
                },
            });
            pixData.value = response as PixResponse;
            startTimer();
            startPolling();
        } catch (error: any) {
            const errorMsg = error?.data?.message || error?.message || 'Erro ao processar pagamento.';
            toast.add({ severity: 'error', summary: 'Erro no pagamento', detail: errorMsg, life: 5000 });
        } finally {
            loadingPayment.value = false;
        }
    }
};
</script>

<template>
    <div class="checkout-page space-y-8">
        <HeroSection
            title="Finalizar compra"
            subtitle="Revise seu pedido, escolha a entrega e conclua o pagamento com segurança."
            :showButton="true"
            buttonLabel="Voltar"
            buttonLinkBack
            buttonIcon="pi pi-arrow-left"
        />

        <!-- Sucesso: cartão/PIX aprovado -->
        <div v-if="paymentSuccess" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 flex flex-col items-center justify-center text-center gap-4">
            <div class="w-20 h-20 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 text-5xl">
                <i class="pi pi-check-circle"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Pagamento realizado com sucesso!</h2>
            <p class="text-gray-500 max-w-sm leading-6">Seu pagamento foi confirmado. Em breve você receberá as etiquetas no endereço cadastrado.</p>
            <NuxtLink to="/dashboard/pedidos" class="mt-2 px-6 py-2.5 bg-it-primary text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">
                Acompanhar meus pedidos
            </NuxtLink>
        </div>

        <!-- Sucesso: boleto gerado -->
        <div v-else-if="boletoData" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 flex flex-col items-center justify-center text-center gap-4">
            <div class="w-20 h-20 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 text-5xl">
                <i class="pi pi-check-circle"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Boleto gerado com sucesso!</h2>
            <p class="text-gray-500 max-w-sm leading-6">Pague até o vencimento para confirmar seu pedido. Após a compensação bancária (até 3 dias úteis), seu pedido será processado.</p>
            <a
                v-if="boletoData.boleto_url"
                :href="boletoData.boleto_url"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-2 px-6 py-2.5 bg-it-primary text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition flex items-center gap-2"
            >
                <i class="pi pi-external-link"></i>
                Abrir boleto
            </a>
            <div v-if="boletoData.barcode" class="w-full max-w-lg text-left mt-2">
                <p class="text-xs font-semibold text-gray-700 mb-1">Código de barras</p>
                <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono text-xs text-gray-700 break-all select-all">{{ boletoData.barcode }}</div>
            </div>
            <NuxtLink to="/dashboard/pedidos" class="text-sm text-it-primary hover:underline">
                Acompanhar meus pedidos
            </NuxtLink>
        </div>

        <div v-else class="checkout-shell relative bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <!-- Badge de Valor Total -->
            <div class="checkout-total-summary bg-emerald-500 text-white rounded-full shadow-lg px-5 py-2.5 z-10">
                <div class="flex items-center gap-3">
                    <div class="flex flex-col">
                        <span class="text-xs font-medium opacity-90">Total</span>
                        <span class="text-xl font-bold">R$ {{ displayTotal.toFixed(2) }}</span>
                    </div>
                    <div class="w-px h-8 bg-white/30"></div>
                    <div class="flex flex-col text-xs">
                        <span class="opacity-80">{{ selectedVehicles.length }} veículo{{ selectedVehicles.length > 1 ? 's' : '' }}</span>
                        <span class="opacity-80">+ frete R$ {{ frete.toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <div v-if="shippingError" role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">Não foi possível confirmar o frete. <button type="button" class="font-semibold underline" @click="fetchShippingSettings">Tentar novamente</button></div>
            <Stepper value="1" class="w-full">
                <!-- Step 1: Entrega/Retirada -->
                <StepItem value="1">
                    <Step>Entrega / Retirada</Step>
                    <StepPanel :value="1">
                        <template #default="{ activateCallback }">
                            <div class="space-y-6">
                            <h3 class="text-lg! font-semibold text-gray-900">Opção de Entrega/Retirada</h3>
                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    :class="[
                                        'checkout-option-button px-4 py-2 rounded-lg border text-sm font-medium flex items-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed',
                                        entrega === 'casa' ? 'border-it-primary text-it-primary bg-blue-50' : 'border-gray-200 text-gray-700 hover:border-it-primary'
                                    ]"
                                    @click="entrega = 'casa'"
                                >
                                    <i class="pi pi-home"></i>
                                    Receber em Casa
                                </button>
                                <span v-tooltip.top="pontosColetaOptions.length === 0 ? 'Nenhum ponto de coleta disponível no momento.' : undefined" class="inline-flex">
                                    <button
                                        type="button"
                                        :disabled="pontosColetaOptions.length === 0"
                                        :class="[
                                            'checkout-option-button px-4 py-2 rounded-lg border text-sm font-medium flex items-center gap-2 transition',
                                            pontosColetaOptions.length === 0
                                                ? 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed opacity-60'
                                                : entrega === 'ponto' ? 'border-it-primary text-it-primary bg-blue-50' : 'border-gray-200 text-gray-700 hover:border-it-primary'
                                        ]"
                                        @click="pontosColetaOptions.length > 0 && (entrega = 'ponto')"
                                    >
                                        <i class="pi pi-building"></i>
                                        Retirar em Ponto de Coleta
                                    </button>
                                </span>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                                <div class="checkout-delivery-form lg:col-span-3 space-y-4">
                                    <div v-if="entrega === 'casa'">
                                        <p class="mb-4 text-sm text-gray-600">Preencha os campos com *. O CEP é opcional: você pode digitar o endereço completo sem ele.</p>
                                        <p v-if="deliveryValidationShown && missingDeliveryFields.length" role="alert" class="mb-4 text-sm text-red-700">Preencha os campos obrigatórios: {{ missingDeliveryFields.join(', ') }}.</p>
                                        <div v-if="cepError" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 mb-4">
                                            {{ cepError }}
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-10 gap-4">
                                            <InputText
                                                v-model="endereco.cep"
                                                label="CEP (opcional)"
                                                id="delivery-cep"
                                                placeholder="00000-000"
                                                mask="99999-999"
                                                icon="pi pi-map-marker"
                                                @change="(e) => handleCepChange((e.target as HTMLInputElement).value)"
                                                :disabled="loadingCep"
                                                wrapper-class="lg:col-span-3"
                                                inputClass="w-full"
                                            />
                                            <div class="md:col-span-2 lg:col-span-7" :class="{ 'delivery-field-error': deliveryFieldInvalid('rua') }">
                                                <InputText v-model="endereco.rua" label="Logradouro" id="delivery-rua" required placeholder="Av. Paulista" icon="pi pi-road" inputClass="w-full" />
                                                <p v-if="deliveryFieldInvalid('rua')" role="alert" class="mt-1 text-sm text-red-700">Informe o logradouro.</p>
                                            </div>
                                            <div class="lg:col-span-5" :class="{ 'delivery-field-error': deliveryFieldInvalid('numero') }">
                                                <InputText v-model="endereco.numero" label="Número" id="delivery-numero" required placeholder="1000 ou s/n" icon="pi pi-hashtag" inputClass="w-full" />
                                                <p v-if="deliveryFieldInvalid('numero')" role="alert" class="mt-1 text-sm text-red-700">Informe o número.</p>
                                            </div>
                                            <InputText v-model="endereco.complemento" label="Complemento" placeholder="Apto 101" icon="pi pi-building" wrapper-class="lg:col-span-5" inputClass="w-full" />
                                            <div class="lg:col-span-5" :class="{ 'delivery-field-error': deliveryFieldInvalid('bairro') }">
                                                <InputText v-model="endereco.bairro" label="Bairro" id="delivery-bairro" required placeholder="Bela Vista" icon="pi pi-map" inputClass="w-full" />
                                                <p v-if="deliveryFieldInvalid('bairro')" role="alert" class="mt-1 text-sm text-red-700">Informe o bairro.</p>
                                            </div>
                                            <div class="lg:col-span-5" :class="{ 'delivery-field-error': deliveryFieldInvalid('cidade') }">
                                                <InputText v-model="endereco.cidade" label="Cidade" id="delivery-cidade" required placeholder="São Paulo" icon="pi pi-building" inputClass="w-full" />
                                                <p v-if="deliveryFieldInvalid('cidade')" role="alert" class="mt-1 text-sm text-red-700">Informe a cidade.</p>
                                            </div>
                                            <div class="lg:col-span-5" :class="{ 'delivery-field-error': deliveryFieldInvalid('estado') }">
                                                <SelectInput v-model="endereco.estado" label="Estado" id="delivery-estado" required :options="estados" placeholder="Selecione o estado" icon="pi pi-map-marker" inputClass="w-full" />
                                                <p v-if="deliveryFieldInvalid('estado')" role="alert" class="mt-1 text-sm text-red-700">Informe o estado.</p>
                                            </div>
                                            <div class="lg:col-span-5 flex flex-col justify-end">
                                                <label class="block text-sm font-semibold text-gray-900 mb-2">Frete</label>
                                                <div class="checkout-shipping-field border rounded-lg px-4 flex items-center justify-between">
                                                    <div class="flex items-center gap-2 text-gray-700">
                                                        <i class="pi pi-truck text-it-primary"></i>
                                                        <span class="text-sm font-medium">Custo de Entrega</span>
                                                    </div>
                                                    <span class="text-lg font-bold text-it-primary">{{ shippingLoading ? 'Carregando...' : shippingError ? 'Indisponível' : `R$ ${frete.toFixed(2)}` }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <PickupPointSelector v-else-if="entrega === 'ponto'" v-model="pontoColeta" :points="pickupPoints" :reference-address="pickupReference" :loading="pickupLoading" :error="pickupError" @retry="fetchPickupPoints" />
                                </div>

                                <div class="checkout-delivery-visual lg:col-span-2 hidden md:block">
                                    <div class="bg-gray-100 gap-4 rounded-2xl overflow-hidden h-full flex items-center justify-center min-h-75">
                                        <img :src="assetWithBase('/images/dashboard/post-01.jpg')" alt="Entrega e Retirada" class="w-75" />
                                        <img :src="assetWithBase('/images/dashboard/post-02.jpg')" alt="Entrega e Retirada" class="w-75" />
                                    </div>
                                </div>
                            </div>
                            </div>
                            <div class="flex justify-end pt-6">
                                <Button variant="secondary" label="Continuar" size="sm" icon="pi pi-chevron-right" buttonClass="checkout-step-next" @click="continueDelivery(activateCallback)" />
                            </div>
                        </template>
                    </StepPanel>
                </StepItem>

                <!-- Step 2: Resumo -->
                <StepItem :value="2">
                    <Step>Resumo</Step>
                    <StepPanel :value="2">
                        <template #default="{ activateCallback }">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Kits -->
                                <KitCheckoutSummary :vehicles="selectedVehicles" @remove="removeVehicle" />

                                <!-- Financeiro -->
                                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm space-y-4">
                                    <h3 class="text-lg! color-it-primary font-semibold mb-3">Resumo Financeiro</h3>

                                    <!-- Cupom de desconto -->
                                    <div class="space-y-2">
                                        <label class="text-sm font-semibold text-gray-700 mb-2 block">Cupom de desconto</label>
                                        <div v-if="couponData" class="flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-200 rounded-lg text-sm">
                                            <i class="pi pi-tag text-green-600"></i>
                                            <span class="font-semibold text-green-700 flex-1">{{ couponData.coupon.code }}</span>
                                            <span class="text-green-600 font-medium">-R$ {{ couponData.discount.toFixed(2) }}</span>
                                            <button type="button" @click="removeCoupon" class="text-gray-400 hover:text-red-500 transition ml-1">
                                                <i class="pi pi-times text-xs"></i>
                                            </button>
                                        </div>
                                        <div v-else class="flex gap-2">
                                            <input
                                                v-model="couponCode"
                                                type="text"
                                                placeholder="Digite seu cupom"
                                                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm uppercase tracking-wider focus:outline-none"
                                                :disabled="couponLoading"
                                                @keyup.enter="applyCoupon"
                                            />
                                            <button
                                                type="button"
                                                @click="applyCoupon"
                                                :disabled="couponLoading || !couponCode.trim()"
                                                class="px-4 py-2 bg-it-primary text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition disabled:opacity-50 whitespace-nowrap"
                                            >
                                                <i v-if="couponLoading" class="pi pi-spin pi-spinner mr-1"></i>
                                                Aplicar
                                            </button>
                                        </div>
                                        <p v-if="couponError" class="text-xs text-red-600 flex items-center gap-1">
                                            <i class="pi pi-exclamation-circle"></i>
                                            {{ couponError }}
                                        </p>
                                    </div>

                                    <!-- Valores -->
                                    <div class="space-y-3 text-sm text-gray-700">
                                        <div class="flex justify-between">
                                            <span>Qtd. de unidades</span>
                                            <!-- soma da quantidae de itens por kit -->
                                            <span class="font-semibold">{{ getTotalUnits() }}x</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Subtotal ({{ selectedVehicles.length }}x)</span>
                                            <span class="font-semibold">R$ {{ subtotalKits.toFixed(2) }}</span>
                                        </div>
                                        <div v-if="couponData" class="flex justify-between text-green-600">
                                            <span>Desconto ({{ couponData.coupon.code }})</span>
                                            <span class="font-semibold">-R$ {{ couponData.discount.toFixed(2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Frete</span>
                                            <span class="font-semibold">R$ {{ frete.toFixed(2) }}</span>
                                        </div>
                                        <div class="border-t border-gray-200 pt-3 flex justify-between text-base font-bold text-gray-900">
                                            <span>Total</span>
                                            <span v-if="displayTotal === 0" class="text-green-600">Grátis</span>
                                            <span v-else>R$ {{ displayTotal.toFixed(2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between pt-6 gap-3">
                                <Button label="Voltar" variant="secondary" icon="pi pi-chevron-left" size="sm" buttonClass="checkout-step-back" @click="activateCallback('1')" />
                                <Button label="Continuar" variant="secondary" size="sm" icon="pi pi-chevron-right" buttonClass="checkout-step-next" @click="activateCallback(3)" />
                            </div>
                        </template>
                    </StepPanel>
                </StepItem>

                <!-- Step 3: Pagamento -->
                <StepItem :value="3">
                    <Step>Pagamento</Step>
                    <StepPanel :value="3">
                        <template #default="{ activateCallback }">
                            <div class="space-y-6">
                            <h3 class="text-lg! font-semibold text-gray-900">Forma de Pagamento</h3>

                            <!-- Gratuito: cupom cobre 100% -->
                            <div v-if="isFree" class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700">
                                <i class="pi pi-check-circle text-2xl"></i>
                                <div>
                                    <p class="font-semibold">Pedido 100% gratuito!</p>
                                    <p class="text-sm text-green-600">O cupom <strong>{{ couponData?.coupon.code }}</strong> cobre o valor total. Não é necessário informar forma de pagamento.</p>
                                </div>
                            </div>

                            <div v-else class="flex flex-wrap gap-3">
                                <button
                                    v-if="CREDIT_CARD_ENABLED"
                                    type="button"
                                    :class="[
                                        'checkout-option-button px-4 py-2 rounded-lg border text-sm font-medium flex items-center gap-2 transition',
                                        paymentMethod === 'credito' ? 'border-it-primary text-it-primary bg-blue-50' : 'border-gray-200 text-gray-700 hover:border-it-primary'
                                    ]"
                                    :disabled="!config.public.mercadoPagoPublicKey"
                                    :title="!config.public.mercadoPagoPublicKey ? 'Pagamento por cartão indisponível no momento.' : undefined"
                                    @click="paymentMethod = 'credito'; pixData = null"
                                >
                                    <i class="pi pi-credit-card"></i>
                                    Cartão de Crédito
                                </button>
                                <button
                                    type="button"
                                    :class="[
                                        'checkout-option-button px-4 py-2 rounded-lg border text-sm font-medium flex items-center gap-2 transition',
                                        paymentMethod === 'pix' ? 'border-it-primary text-it-primary bg-blue-50' : 'border-gray-200 text-gray-700 hover:border-it-primary'
                                    ]"
                                    @click="paymentMethod = 'pix'"
                                >
                                    <i class="pi pi-qrcode"></i>
                                    PIX
                                </button>
                                <button
                                    type="button"
                                    :class="[
                                        'checkout-option-button px-4 py-2 rounded-lg border text-sm font-medium flex items-center gap-2 transition',
                                        paymentMethod === 'boleto' ? 'border-it-primary text-it-primary bg-blue-50' : 'border-gray-200 text-gray-700 hover:border-it-primary'
                                    ]"
                                    @click="paymentMethod = 'boleto'; pixData = null"
                                >
                                    <i class="pi pi-bars"></i>
                                    Boleto
                                </button>
                            </div>

                            <!-- Cartão de Crédito -->
                            <div v-if="CREDIT_CARD_ENABLED && !isFree && paymentMethod === 'credito'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="space-y-4 bg-gray-50 border border-gray-200 rounded-xl p-4">
                                    <p class="text-sm font-semibold text-gray-800">Dados do cartão:</p>
                                    <div class="grid grid-cols-1 gap-4">
                                        <InputText v-model="cartao.nome" label="Nome no Cartão" placeholder="Nome como no cartão" icon="pi pi-user" inputClass="w-full" />
                                        <InputText v-model="cartao.numero" label="Número do Cartão" placeholder="0000 0000 0000 0000" mask="9999 9999 9999 9999" icon="pi pi-credit-card" inputClass="w-full" />
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <InputText v-model="cartao.validade" label="Validade (MM/AA)" placeholder="MM/AA" mask="99/99" icon="pi pi-calendar" inputClass="w-full" />
                                            <InputText v-model="cartao.cvc" label="CVC" placeholder="123" mask="999" icon="pi pi-lock" inputClass="w-full" />
                                        </div>
                                        <InputText v-model="cartao.cpf" label="CPF do Titular" placeholder="000.000.000-00" mask="999.999.999-99" icon="pi pi-id-card" inputClass="w-full" />
                                    </div>
                                </div>
                                <div class="hidden lg:flex items-center justify-center">
                                    <CreditCardPreview :numero="cartao.numero" :nome="cartao.nome" :validade="cartao.validade" :cvc="cartao.cvc" />
                                </div>
                            </div>

                            <!-- PIX -->
                            <div v-else-if="!isFree && paymentMethod === 'pix'" class="space-y-4">
                                <!-- Aguardando gerar QR Code -->
                                <div v-if="!pixData" class="space-y-3 bg-gray-50 border border-gray-200 rounded-xl p-4">
                                    <p class="text-sm font-semibold text-gray-800">Pague via PIX:</p>
                                    <p class="text-sm text-gray-600">Clique no botão "Gerar PIX" para gerar o QR Code.</p>
                                </div>

                                <!-- QR Code gerado -->
                                <div v-else class="space-y-4">
                                    <!-- Temporizador -->
                                    <div
                                        class="flex items-center justify-center gap-3 p-3 rounded-xl border"
                                        :class="timerSeconds < 60 ? 'bg-red-50 border-red-200' : 'bg-blue-50 border-blue-200'"
                                    >
                                        <i class="pi pi-clock text-lg" :class="timerSeconds < 60 ? 'text-red-500' : 'text-it-primary'"></i>
                                        <span
                                            class="font-bold text-2xl tabular-nums"
                                            :class="timerSeconds < 60 ? 'text-red-500' : 'text-it-primary'"
                                        >{{ timerDisplay }}</span>
                                        <span class="text-sm text-gray-600">para realizar o pagamento</span>
                                    </div>

                                    <!-- QR Code + Copia e Cola -->
                                    <div class="flex flex-col lg:flex-row gap-6 items-start bg-gray-50 border border-gray-200 rounded-xl p-5">
                                        <!-- QR Code -->
                                        <div class="shrink-0 mx-auto lg:mx-0">
                                            <img
                                                :src="`data:image/png;base64,${pixData.qr_code_base64}`"
                                                alt="QR Code PIX"
                                                class="w-48 h-48 rounded-xl border border-gray-200 bg-white"
                                            />
                                        </div>

                                        <!-- Código -->
                                        <div class="flex-1 space-y-4">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-800 mb-1">Código PIX (Copia e Cola)</p>
                                                <p class="text-xs text-gray-500">Abra seu banco, acesse a área PIX, escolha "Pagar com código" e cole o código abaixo.</p>
                                            </div>
                                            <div class="flex gap-2">
                                                <input
                                                    :value="pixData.qr_code"
                                                    readonly
                                                    class="flex-1 min-w-0 text-xs bg-white border border-gray-200 rounded-lg px-3 py-2.5 font-mono text-gray-700 truncate focus:outline-none"
                                                />
                                                <button
                                                    @click="copyPixCode"
                                                    :class="[
                                                        'px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition whitespace-nowrap',
                                                        copied ? 'bg-emerald-500 text-white' : 'bg-it-primary text-white hover:bg-blue-700'
                                                    ]"
                                                >
                                                    <i :class="copied ? 'pi pi-check' : 'pi pi-copy'"></i>
                                                    {{ copied ? 'Copiado!' : 'Copiar código' }}
                                                </button>
                                            </div>
                                            <p class="text-xs text-gray-500 flex items-center gap-1.5">
                                                <i class="pi pi-info-circle text-it-primary"></i>
                                                Pagamento identificado automaticamente após confirmação.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Boleto -->
                            <div v-else-if="!isFree" class="space-y-4">
                                <!-- Formulário de endereço do pagador -->
                                <div class="space-y-4 bg-gray-50 border border-gray-200 rounded-xl p-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">Endereço do pagador</p>
                                        <p class="text-xs text-gray-500 mt-1">Obrigatório pelo MercadoPago para emissão de boleto bancário.</p>
                                    </div>

                                    <!-- Checkbox: usar endereço de entrega -->
                                    <label
                                        v-if="entrega === 'casa' && isDeliveryValid"
                                        class="flex items-center gap-2 cursor-pointer select-none"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="boletoUsarEnderecoEntrega"
                                            class="w-4 h-4 accent-it-primary cursor-pointer"
                                        />
                                        <span class="text-sm text-gray-700">Usar mesmo endereço de entrega</span>
                                    </label>

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-10 gap-4">
                                        <InputText
                                            v-model="boletoEndereco.cep"
                                            label="CEP"
                                            placeholder="00000-000"
                                            mask="99999-999"
                                            icon="pi pi-map-marker"
                                            :disabled="boletoUsarEnderecoEntrega || loadingCep"
                                            @change="(e: Event) => handleBoletoCepChange((e.target as HTMLInputElement).value)"
                                            wrapper-class="lg:col-span-3"
                                            inputClass="w-full"
                                        />
                                        <InputText
                                            v-model="boletoEndereco.rua"
                                            label="Logradouro"
                                            placeholder="Av. Paulista"
                                            icon="pi pi-road"
                                            :readonly="true"
                                            :disabled="boletoUsarEnderecoEntrega"
                                            wrapper-class="md:col-span-2 lg:col-span-7"
                                            inputClass="w-full"
                                        />
                                        <InputText
                                            v-model="boletoEndereco.numero"
                                            label="Número"
                                            placeholder="1000"
                                            type="number"
                                            icon="pi pi-hashtag"
                                            :disabled="boletoUsarEnderecoEntrega"
                                            wrapper-class="lg:col-span-5"
                                            inputClass="w-full"
                                        />
                                        <InputText
                                            v-model="boletoEndereco.bairro"
                                            label="Bairro"
                                            placeholder="Bela Vista"
                                            icon="pi pi-map"
                                            :readonly="true"
                                            :disabled="boletoUsarEnderecoEntrega"
                                            wrapper-class="lg:col-span-5"
                                            inputClass="w-full"
                                        />
                                        <InputText
                                            v-model="boletoEndereco.cidade"
                                            label="Cidade"
                                            placeholder="São Paulo"
                                            icon="pi pi-building"
                                            :readonly="true"
                                            :disabled="boletoUsarEnderecoEntrega"
                                            wrapper-class="lg:col-span-5"
                                            inputClass="w-full"
                                        />
                                        <SelectInput
                                            v-model="boletoEndereco.estado"
                                            label="Estado"
                                            :options="estados"
                                            placeholder="Selecione o estado"
                                            icon="pi pi-map-marker"
                                            :readonly="true"
                                            :disabled="boletoUsarEnderecoEntrega"
                                            wrapper-class="lg:col-span-5"
                                            inputClass="w-full"
                                        />
                                    </div>
                                </div>
                            </div>
                            </div>

                            <div class="flex justify-start pt-6 gap-3">
                                <Button label="Voltar" variant="secondary" icon="pi pi-chevron-left" size="sm" buttonClass="checkout-payment-back" @click="activateCallback(2)" />
                                <span
                                    v-if="(paymentMethod !== 'pix' || !pixData) && (paymentMethod !== 'boleto' || !boletoData)"
                                    v-tooltip.top="paymentButtonTooltip"
                                    class="inline-flex"
                                >
                                    <Button
                                        :label="isFree ? 'Confirmar Pedido' : paymentMethod === 'pix' ? 'Gerar PIX para Pagamento' : paymentMethod === 'boleto' ? 'Gerar Boleto para Pagamento' : 'Realizar Pagamento'"
                                        :icon="isFree ? 'pi pi-check-circle' : paymentMethod === 'pix' ? 'pi pi-qrcode' : paymentMethod === 'boleto' ? 'pi pi-bars' : 'pi pi-check'"
                                        size="sm"
                                        buttonClass="checkout-payment-action"
                                        :loading="loadingPayment"
                                        :disabled="isPaymentButtonDisabled"
                                        @click="handlePagar"
                                    />
                                </span>
                            </div>
                        </template>
                    </StepPanel>
                </StepItem>
            </Stepper>
        </div>
    </div>
</template>

<style scoped>
.delivery-field-error :deep(input),
.delivery-field-error :deep(.p-select) {
    border-color: #dc2626 !important;
    box-shadow: 0 0 0 1px #dc2626 !important;
}

.checkout-page {
    --checkout-blue: #1f46ee;
    --checkout-ink: #172b4d;
}

.checkout-shell {
    padding: 2rem !important;
    border-color: #e0e7f0 !important;
    border-radius: 1.15rem !important;
    box-shadow: none !important;
}

.checkout-total-summary {
    position: static;
    display: inline-flex;
    margin: 0 0 1.75rem auto;
    border: 1px solid #3159ee;
    border-radius: 0.85rem;
    background: #1f46ee !important;
    box-shadow: none !important;
}

.checkout-total-summary > div { min-height: 3.15rem; }
.checkout-page :deep(.p-stepper) { width: 100%; }

.checkout-page :deep(.p-stepper-nav),
.checkout-page :deep(.p-steplist) {
    margin-bottom: 1.75rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e4e9f1;
}

.checkout-page :deep(.p-stepper-action),
.checkout-page :deep(.p-step-header) {
    gap: 0.55rem;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 600;
}

.checkout-page :deep(.p-stepper-number),
.checkout-page :deep(.p-step-number) {
    width: 2rem;
    height: 2rem;
    border: 1px solid #d8e0eb;
    border-radius: 50%;
    background: #fff;
    color: #64748b;
}

.checkout-page :deep(.p-stepper-header.p-highlight .p-stepper-number),
.checkout-page :deep(.p-step.p-highlight .p-step-number) {
    border-color: #1f46ee;
    background: #eaf0ff;
    color: #1f46ee;
}

.checkout-page h3 { color: var(--checkout-ink); font-weight: 700; letter-spacing: -0.01em; }
.checkout-page :deep(label) { color: var(--checkout-ink); font-weight: 600; }

.checkout-page :deep(input),
.checkout-page :deep(.p-select) {
    min-height: 3rem;
    border-color: #cbd7e8;
    border-radius: 0.65rem;
}

.checkout-page :deep(input:focus),
.checkout-page :deep(.p-select.p-focus) {
    border-color: #8499bd;
    box-shadow: 0 0 0 3px rgba(31, 70, 238, 0.08);
}

.checkout-delivery-form { grid-column: 1 / -1 !important; }
.checkout-delivery-visual { display: none !important; }

.checkout-shipping-field {
    height: 48px;
    min-height: 48px;
    border-color: #cbd7e8 !important;
    background: #fafafa;
}

.checkout-shipping-field > div { color: #52667f !important; }
.checkout-shipping-field > span { color: #172b4d !important; font-size: 0.95rem !important; }
.checkout-shipping-field i { color: #64748b !important; }

.checkout-summary-tag {
    width: 3.35rem;
    height: 3.35rem;
    flex: 0 0 auto;
    object-fit: contain;
    border: 1px solid #e1e7f0;
    border-radius: 0.7rem;
    background: #fff;
    padding: 0.3rem;
}

.checkout-page :deep(button.checkout-step-next),
.checkout-page :deep(button.checkout-step-back),
.checkout-page :deep(button.checkout-payment-back),
.checkout-page :deep(button.checkout-payment-action) {
    min-height: 3.25rem !important;
    height: 3.25rem !important;
    padding: 0 1.1rem !important;
    border-radius: 0.65rem !important;
    box-shadow: none !important;
    font-size: 1rem !important;
}

.checkout-page :deep(button.checkout-step-next) {
    border: 1px solid #dce3eb !important;
    background: #fafafa !important;
    color: #172b4d !important;
}

.checkout-page :deep(button.checkout-step-next:hover) { background: #f2f4f7 !important; }
.checkout-page :deep(button.checkout-step-next i) { order: 2; }

.checkout-page :deep(button.checkout-step-back),
.checkout-page :deep(button.checkout-payment-back) {
    border: 1px solid #cdd7e3 !important;
    background: #fafafa !important;
    color: #172b4d !important;
}

.checkout-page :deep(button.checkout-step-back:hover),
.checkout-page :deep(button.checkout-payment-back:hover) { background: #f5f7fa !important; }

.checkout-page :deep(button.checkout-payment-action) {
    border: 1px solid #3159ee !important;
    background: #1f46ee !important;
    color: #fff !important;
}

.checkout-page :deep(button.checkout-payment-action:hover) { background: #1839c9 !important; }

.checkout-page :deep(.bg-gray-50) {
    border-color: #e2e8f0 !important;
    background: #fafafa !important;
}

.checkout-page :deep(.shadow-sm),
.checkout-page :deep(.shadow-lg) { box-shadow: none !important; }

@media (max-width: 700px) {
    .checkout-shell { padding: 1.25rem !important; border-radius: 1rem !important; }
    .checkout-total-summary { display: flex; width: 100%; justify-content: center; margin-bottom: 1.25rem; }
    .checkout-page :deep(.p-stepper-nav), .checkout-page :deep(.p-steplist) { overflow-x: auto; gap: 1rem; }
    .checkout-page :deep(.p-stepper-header), .checkout-page :deep(.p-step) { flex: 0 0 auto; }
    .checkout-page :deep(.p-stepper-action), .checkout-page :deep(.p-step-header) { font-size: 0.8rem; white-space: nowrap; }
    .checkout-page :deep(.checkout-option-button),
    .checkout-page :deep(input),
    .checkout-page :deep(.p-select),
    .checkout-shipping-field {
        height: 52px !important;
        min-height: 52px !important;
    }
    .checkout-page :deep(button.checkout-step-next),
    .checkout-page :deep(button.checkout-step-back),
    .checkout-page :deep(button.checkout-payment-back),
    .checkout-page :deep(button.checkout-payment-action) {
        height: 52px !important;
        min-height: 52px !important;
    }
    .checkout-page :deep(.flex.justify-end.pt-6), .checkout-page :deep(.flex.justify-between.pt-6), .checkout-page :deep(.flex.justify-start.pt-6) { flex-direction: column-reverse; }
    .checkout-page :deep(.flex.justify-end.pt-6 button), .checkout-page :deep(.flex.justify-between.pt-6 button), .checkout-page :deep(.flex.justify-start.pt-6 button) { width: 100%; justify-content: center; }
}
</style>
