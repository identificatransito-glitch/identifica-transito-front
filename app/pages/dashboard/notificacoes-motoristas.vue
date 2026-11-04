<script setup lang="ts">
import { CircleDot, DoorOpen, Lightbulb, PackageOpen, Phone } from '@lucide/vue';
import HeroSection from '~/components/dashboard/HeroSection.vue';
import InputText from '~/components/forms/InputText.vue';
import SelectInput from '~/components/forms/SelectInput.vue';
import Button from '~/components/forms/Button.vue';
import Skeleton from 'primevue/skeleton';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { getDashboardByType, getUserType } from '~/utils/authRedirect';

definePageMeta({
    layout: 'dashboard',
    middleware: [() => {
        if (import.meta.server) return;

        const auth = useAuth();
        auth.init();

        const userType = getUserType(auth.user.value);
        if (userType !== 3) {
            return navigateTo(getDashboardByType(userType));
        }
    }]
});

useHead({
    title: 'Notificações de Motoristas - Identifica Trânsito',
    meta: [
        { name: 'description', content: 'Notifique motoristas sobre situações observadas no veículo.' }
    ]
});

interface DriverNotification {
    id: number;
    plate: string;
    type: number;
    typeLabel: string;
    createdAt: string;
    read: boolean;
}

interface ApiDriverNotification {
    id: number;
    user_id: number;
    plate: string;
    type: number;
    type_label: string;
    read: number | string | boolean;
    created_at: string;
    updated_at: string;
}

type NotificationsTab = 'sent' | 'received';

const form = ref({
    placa: '',
    tipo: null as number | null
});

const confirm = useConfirm();
const toast = useToast();
const { $api } = useNuxtApp();
const loading = ref(false);
const unreadNotificationsCount = useState<number>('driver_notifications_unread_total', () => 0);
const activeTab = ref<NotificationsTab>('received');

const typeOptions = [
    { label: 'Pneu murcho/furado', value: 1 },
    { label: 'Farol ou lanterna queimada', value: 2 },
    { label: 'Porta Aberta', value: 3 },
    { label: 'Algo caindo do carro', value: 4 }
];

const notificationTypeIcon = (type: number) => ({
    1: CircleDot,
    2: Lightbulb,
    3: DoorOpen,
    4: PackageOpen
}[type] || Phone);

const sentNotifications = ref<DriverNotification[]>([]);
const receivedNotifications = ref<DriverNotification[]>([]);

const totalUnread = computed(() => receivedNotifications.value.filter((item) => !item.read).length);

const normalizePlate = (value: string) => {
    return value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
};

const nowFormatted = () => {
    return new Date().toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const toBool = (value: number | string | boolean | null | undefined) => {
    return value === true || value === 1 || value === '1';
};

const resolveTypeLabel = (type: number, typeLabel?: string) => {
    if (typeLabel) return typeLabel;
    return typeOptions.find((option) => option.value === type)?.label || 'Tipo não informado';
};

const mapNotification = (item: ApiDriverNotification): DriverNotification => ({
    id: item.id,
    plate: item.plate,
    type: Number(item.type),
    typeLabel: resolveTypeLabel(Number(item.type), item.type_label),
    createdAt: item.created_at,
    read: toBool(item.read)
});

const extractNotificationList = (res: { data?: ApiDriverNotification[] } | ApiDriverNotification[]) => {
    return (res as { data?: ApiDriverNotification[] })?.data ?? (Array.isArray(res) ? res : []);
};

const fetchUnreadTotal = async () => {
    try {
        const res = await $api('/notifications-drivers/unread-total') as { unread_total?: number | string };
        unreadNotificationsCount.value = Number(res?.unread_total ?? 0);
    } catch {
        unreadNotificationsCount.value = totalUnread.value;
    }
};

const notifyUnreadCountChanged = () => {
    if (import.meta.client) {
        window.dispatchEvent(new CustomEvent('driver-notifications-unread-changed'));
    }
};

const fetchNotifications = async () => {
    loading.value = true;
    sentNotifications.value = [];
    receivedNotifications.value = [];

    let hasAnyError = false;

    try {
        const sentRes = await $api('/notifications-drivers') as { data?: ApiDriverNotification[] } | ApiDriverNotification[];
        const sentList = extractNotificationList(sentRes);
        sentNotifications.value = sentList.map(mapNotification);
    } catch {
        hasAnyError = true;
    }

    try {
        const receivedRes = await $api('/notifications-drivers/received') as { data?: ApiDriverNotification[] } | ApiDriverNotification[];
        const receivedList = extractNotificationList(receivedRes);
        receivedNotifications.value = receivedList.map(mapNotification);
    } catch {
        hasAnyError = true;
    }

    try {
        await fetchUnreadTotal();
        notifyUnreadCountChanged();
    } catch {
        hasAnyError = true;
    }

    if (hasAnyError && sentNotifications.value.length === 0 && receivedNotifications.value.length === 0) {
        toast.add({
            severity: 'error',
            summary: 'Erro ao carregar',
            detail: 'Não foi possível carregar as notificações.',
            life: 5000
        });
    }

    loading.value = false;
};

const isSending = ref(false);

const handleSubmit = async () => {
    if (isSending.value) return;
    const plate = normalizePlate(form.value.placa);
    if (!plate || !form.value.tipo) return;
    isSending.value = true;

    try {
        const created = await $api('/notifications-drivers', {
            method: 'POST',
            body: {
                plate,
                type: form.value.tipo
            }
        }) as { data?: ApiDriverNotification } | ApiDriverNotification;

        const payload = (created as { data?: ApiDriverNotification })?.data ?? (created as ApiDriverNotification);
        sentNotifications.value.unshift(mapNotification(payload));
        await fetchUnreadTotal();

        form.value.placa = '';
        form.value.tipo = null;

        toast.add({
            severity: 'success',
            summary: 'Sucesso!',
            detail: 'Notificação registrada. O aviso será enviado ao e-mail vinculado à placa.',
            life: 3000
        });
    } catch (error: any) {
        const msg = error?.data?.errors?.plate?.[0] || error?.data?.message || error?.message || 'Não foi possível enviar a notificação.';
        toast.add({ severity: 'error', summary: 'Erro ao enviar', detail: msg, life: 5000 });
    } finally {
        isSending.value = false;
    }
};

const readLabel = (read: boolean) => (read ? 'Lido' : 'Não Lido');

const formatNotificationDate = (value: string) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return `${date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })}h`;
};

const handleDeleteNotification = (item: DriverNotification) => {
    confirm.require({
        message: `Tem certeza que deseja excluir a notificação de "${item.typeLabel}" para a placa ${item.plate}?`,
        header: 'Confirmar Exclusão',
        styleClass: 'driver-notification-confirm',
        dismissableMask: true,
        rejectLabel: 'Cancelar',
        acceptLabel: 'Excluir notificação',
        rejectClass: 'p-button-secondary',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await $api(`/notifications-drivers/${item.id}`, { method: 'DELETE' });
                sentNotifications.value = sentNotifications.value.filter((n) => n.id !== item.id);
                receivedNotifications.value = receivedNotifications.value.filter((n) => n.id !== item.id);
                await fetchUnreadTotal();
                notifyUnreadCountChanged();
                toast.add({
                    severity: 'success',
                    summary: 'Sucesso!',
                    detail: 'Notificação excluída com sucesso.',
                    life: 3000
                });
            } catch (error: any) {
                const msg = error?.data?.message || error?.message || 'Não foi possível excluir a notificação.';
                toast.add({ severity: 'error', summary: 'Erro ao excluir', detail: msg, life: 5000 });
            }
        }
    });
};

const handleToggleRead = async (item: DriverNotification, read: boolean) => {
    const previous = item.read;
    item.read = read;
    try {
        await $api(`/notifications-drivers/${item.id}`, {
            method: 'PUT',
            body: {
                read: read ? 1 : 0
            }
        });
        await fetchNotifications();
        notifyUnreadCountChanged();
    } catch (error: any) {
        item.read = previous;
        const msg = error?.data?.message || error?.message || 'Não foi possível atualizar o status da notificação.';
        toast.add({ severity: 'error', summary: 'Erro ao atualizar', detail: msg, life: 5000 });
    }
};

const onReadToggle = (item: DriverNotification, value: boolean | string | number | null | undefined) => {
    handleToggleRead(item, toBool(value));
};

onMounted(() => {
    fetchNotifications();
});
</script>

<template>
    <div class="driver-notifications-page space-y-8">
        <HeroSection
            title="Notificações de Motoristas"
            subtitle="Registre alertas rápidos para ajudar outros condutores"
            greeting="Notificar um Motorista"
            :greeting-icon="Phone"
            :showButton="false"
        />

        <section class="bg-white dark:bg-surface-800 rounded-2xl p-8 shadow-sm border border-gray-100 dark:border-surface-700">
            <div class="driver-compose-heading mb-6">
                <span class="driver-heading-icon"><Phone :size="19" :stroke-width="1.9" /></span>
                <h2 class="text-2xl! font-bold text-gray-900 dark:text-white mb-1!">Nova notificação</h2>
                <p class="text-gray-600 dark:text-gray-400">Informe uma placa cadastrada. O aviso será enviado ao e-mail do usuário vinculado ao veículo.</p>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <InputText
                        v-model="form.placa"
                        id="placa"
                        label="Placa do Veículo"
                        placeholder="AAA0A00 ou AAA0000"
                        required
                        showIcon
                        icon="pi pi-car"
                        mask="aaa9*99"
                        :slotChar="''"
                        inputClass="w-full px-4 py-3 uppercase border border-gray-300 rounded-lg focus:outline-none placeholder-gray-400"
                    />

                    <SelectInput
                        v-model="form.tipo"
                        id="tipo"
                        label="Tipo"
                        :options="typeOptions"
                        placeholder="Selecione o tipo"
                        required
                        icon="pi pi-exclamation-triangle"
                    />
                </div>

                <div class="flex justify-end">
                    <Button type="submit" icon="pi pi-send" label="Enviar notificação" :loading="isSending" :disabled="isSending" />
                </div>
            </form>
        </section>

        <section class="driver-notification-history space-y-4">
            <div class="driver-history-title-row flex items-center justify-between">
                <div>
                    <h2 class="text-2xl! font-bold text-gray-900 dark:text-white mb-1!">Notificações</h2>
                    <p class="text-gray-600 dark:text-gray-400">Acompanhe notificações enviadas e recebidas no Identifica Trânsito.</p>
                </div>
                <span v-if="unreadNotificationsCount > 0" class="px-3 py-1 rounded-full text-sm font-semibold bg-orange-100 text-orange-700">
                    {{ unreadNotificationsCount }} não lida(s)
                </span>
            </div>

            <div class="inline-flex rounded-xl border border-gray-200 dark:border-surface-700 p-1 bg-gray-50 dark:bg-surface-800">
                <button
                    class="px-4 py-2 text-sm font-semibold rounded-lg transition"
                    :class="activeTab === 'received' ? 'driver-tab--active bg-white dark:bg-surface-700 text-it-primary shadow-sm' : 'driver-tab--inactive text-gray-600 dark:text-gray-300 hover:text-it-primary'"
                    @click="activeTab = 'received'"
                >
                    Notificações recebidas
                </button>
                <button
                    class="px-4 py-2 text-sm font-semibold rounded-lg transition"
                    :class="activeTab === 'sent' ? 'driver-tab--active bg-white dark:bg-surface-700 text-it-primary shadow-sm' : 'driver-tab--inactive text-gray-600 dark:text-gray-300 hover:text-it-primary'"
                    @click="activeTab = 'sent'"
                >
                    Notificações enviadas
                </button>
            </div>

            <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div v-for="n in 4" :key="`notification-skeleton-${n}`" class="bg-white dark:bg-surface-800 rounded-xl p-5 border border-gray-100 dark:border-surface-700 shadow-sm space-y-3">
                    <Skeleton width="70%" height="16px" />
                    <Skeleton width="40%" height="14px" />
                    <Skeleton width="50%" height="14px" />
                    <Skeleton width="100%" height="1px" />
                    <Skeleton width="35%" height="16px" />
                </div>
            </div>

            <div v-else-if="activeTab === 'sent' && sentNotifications.length > 0" class="driver-notification-grid grid grid-cols-1 lg:grid-cols-2 gap-4">
                <article
                    v-for="item in sentNotifications"
                    :key="item.id"
                    class="driver-notification-card bg-white dark:bg-surface-800 rounded-xl p-5 border border-gray-100 dark:border-surface-700 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-it-primary">
                                <component :is="notificationTypeIcon(item.type)" :size="18" :stroke-width="1.8" />
                                <span class="text-sm font-semibold">{{ item.typeLabel }}</span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Placa: <span class="font-semibold text-gray-900 dark:text-white">{{ item.plate || 'Não informada' }}</span></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Data: <span>{{ formatNotificationDate(item.createdAt) }}</span></p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                @click="handleDeleteNotification(item)"
                                class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition"
                                title="Excluir notificação"
                            >
                                <i class="pi pi-trash text-sm"></i>
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else-if="activeTab === 'received' && receivedNotifications.length > 0" class="driver-notification-grid grid grid-cols-1 lg:grid-cols-2 gap-4">
                <article
                    v-for="item in receivedNotifications"
                    :key="item.id"
                    class="driver-notification-card bg-white dark:bg-surface-800 rounded-xl p-5 border border-gray-100 dark:border-surface-700 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-it-primary">
                                <component :is="notificationTypeIcon(item.type)" :size="18" :stroke-width="1.8" />
                                <span class="text-sm font-semibold">{{ item.typeLabel }}</span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Placa: <span class="font-semibold text-gray-900 dark:text-white">{{ item.plate || 'Não informada' }}</span></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Data: <span>{{ formatNotificationDate(item.createdAt) }}</span></p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                @click="handleDeleteNotification(item)"
                                class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition"
                                title="Excluir notificação"
                            >
                                <i class="pi pi-trash text-sm"></i>
                            </button>
                        </div>
                    </div>

                </article>
            </div>

            <div v-else class="driver-notification-empty flex flex-col items-center justify-center py-12 gap-2 text-gray-400 bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700">
                <Phone :size="25" :stroke-width="1.7" />
                <p>{{ activeTab === 'sent' ? 'Não há notificações enviadas' : 'Não há notificações recebidas' }}</p>
                <p class="text-sm">Quando houver notificações, elas aparecerão aqui nesta aba.</p>
            </div>
        </section>
    </div>
</template>

<style scoped>
.driver-notifications-page {
    --driver-blue: #1f46ee;
    --driver-ink: #172b4d;
    --driver-muted: #60718a;
}

.driver-compose-heading {
    display: flex;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center;
    gap: 0.75rem;
}

.driver-compose-heading > h2 {
    width: auto !important;
    flex: 0 1 auto;
    margin: 0 !important;
}

.driver-heading-icon {
    display: inline-flex;
    width: 2.5rem;
    height: 2.5rem;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    border-radius: 0.75rem;
    color: var(--driver-blue);
    background: #eaf0ff;
}

.driver-compose-heading p {
    display: none;
}

.driver-notifications-page > section:nth-of-type(1) {
    padding: 2rem;
    border: 1px solid #e1e7f0;
    border-radius: 1.15rem;
    background: #fff;
}

.driver-notifications-page > section:nth-of-type(1) > div:first-child {
    margin-bottom: 1.75rem;
}

.driver-notifications-page h2 {
    color: var(--driver-ink);
    font-size: 1.35rem !important;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.driver-notifications-page section p {
    color: var(--driver-muted);
    font-size: 0.9rem;
}

.driver-notifications-page form > div:first-child {
    gap: 1.25rem;
}

.driver-notifications-page form > div:last-child {
    margin-top: 1.5rem;
}

.driver-notifications-page form :deep(label) {
    color: var(--driver-ink);
    font-weight: 600;
}

.driver-notifications-page form :deep(input),
.driver-notifications-page form :deep(.p-select) {
    min-height: 3rem;
    border-color: #cbd7e8;
    border-radius: 0.65rem;
}

.driver-notifications-page form :deep(input:focus),
.driver-notifications-page form :deep(.p-select.p-focus) {
    border-color: #8499bd;
    box-shadow: 0 0 0 3px rgba(31, 70, 238, 0.08);
}

.driver-notifications-page form :deep(button[type='submit']) {
    min-height: 3rem;
    border-radius: 0.65rem;
    background: #1f46ee !important;
    transition: background-color 160ms ease;
}

.driver-notifications-page form :deep(button[type='submit']:hover) {
    background: #1839c9 !important;
    transform: none;
}

.driver-notifications-page > section:nth-of-type(2) {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.driver-notifications-page > section:nth-of-type(2) > div:first-child {
    display: flex;
    align-items: flex-end;
    justify-content: flex-start;
    gap: 1rem;
}

.driver-history-title-row > div {
    min-width: 0;
}

.driver-history-title-row .driver-heading-icon {
    width: 2.25rem;
    height: 2.25rem;
}

.driver-notifications-page > section:nth-of-type(2) > div:first-child h2 {
    margin-bottom: 0.2rem !important;
}

.driver-history-title-row h2 {
    margin-bottom: 0.2rem !important;
}

.driver-notifications-page > section:nth-of-type(2) > div:first-child p {
    margin: 0;
}

.driver-history-title-row p {
    margin: 0;
}

.driver-notification-history > .driver-history-title-row {
    position: relative;
    justify-content: flex-start;
    gap: 1rem;
}

.driver-notification-history > .driver-history-title-row > span:last-child {
    position: absolute;
    right: 0;
    bottom: -3.55rem;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    min-height: 2.25rem;
    margin-left: 0;
    padding: 0.35rem 0.8rem;
    border: 1px solid #d7dee8;
    border-radius: 999px;
    background: linear-gradient(180deg, #f9fafc 0%, #edf1f6 100%) !important;
    color: #526176 !important;
    font-size: 0.78rem;
    font-weight: 600;
}

.driver-notification-history > .inline-flex button {
    color: #9aa6b6 !important;
    opacity: 0.6;
}

.driver-notification-history > .inline-flex button[class*='text-it-primary'] {
    color: #173b9c !important;
    opacity: 1;
}

.driver-notification-history > .inline-flex .driver-tab--inactive {
    opacity: 0.6 !important;
}

.driver-notification-history > .inline-flex .driver-tab--active {
    opacity: 1 !important;
}

.driver-notification-history > .inline-flex {
    min-height: 2.75rem;
    border-color: #dfe5ed;
    background: #f7f8fa;
}

.driver-notification-history > .inline-flex button[class*='bg-white'] {
    background: #fff;
    box-shadow: none;
}

.driver-notification-history article {
    border-color: #e0e6ef;
    background: #fff;
}

.driver-notification-empty {
    min-height: 15rem;
    border-color: #e0e6ef !important;
    background: #fff !important;
    color: #64748b !important;
}

.driver-notification-empty svg { color: #8091a9; }
.driver-notification-empty p:first-of-type { margin-top: 0.25rem; color: #334155; font-size: 0.95rem; font-weight: 600; }
.driver-notification-empty p:last-of-type { color: #708198; font-size: 0.84rem; }

.driver-notification-grid {
    gap: 0.9rem;
}

.driver-notification-card {
    min-height: 10.25rem;
    padding: 1.15rem !important;
    border-color: #e0e7f0 !important;
    border-radius: 0.9rem !important;
    box-shadow: none !important;
    transition: border-color 160ms ease, background-color 160ms ease;
}

.driver-notification-card:hover {
    border-color: #c9d5e5 !important;
    background: #fcfdff !important;
}

.driver-notification-card .space-y-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}

.driver-notification-card .space-y-2 > div:first-child {
    grid-column: 1 / -1;
    align-items: center;
    min-height: 2.25rem;
    margin-bottom: 0.25rem;
    color: #173b9c !important;
    font-size: 0.92rem;
}

.driver-notification-card .space-y-2 > div:first-child span {
    font-size: 1rem !important;
    font-weight: 700;
}

.driver-notification-card .space-y-2 > div:first-child svg {
    display: inline-flex;
    width: 2.1rem;
    height: 2.1rem;
    padding: 0.5rem;
    border-radius: 0.65rem;
    background: #eaf0ff;
}

.driver-notification-card .space-y-2 p {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-height: 3.35rem;
    margin: 0 !important;
    padding: 0.55rem 0.65rem;
    border: 1px solid #e6ebf2;
    border-radius: 0.65rem;
    background: #fafafa;
    color: #718198 !important;
    font-size: 0.73rem !important;
}

.driver-notification-card .space-y-2 p span {
    color: #243a5a !important;
    font-size: 0.9rem;
    font-weight: 650;
}

.driver-notification-card button[title] {
    display: inline-flex;
    width: 2.35rem;
    height: 2.35rem;
    align-items: center;
    justify-content: center;
    border: 1px solid #f7cbd2;
    border-radius: 0.65rem;
    color: #e11d48 !important;
    background: #fff;
}

.driver-notification-card button[title]:hover {
    background: #fff1f2 !important;
}

.driver-notifications-page > section:nth-of-type(2) > div:nth-child(2) {
    align-self: flex-start;
    border-color: #dbe3ee;
    background: #f7f9fc;
}

.driver-notifications-page > section:nth-of-type(2) > div:first-child > .driver-unread-badge,
.driver-notifications-page > section:nth-of-type(2) > div:first-child > span:last-child {
    margin-left: auto;
    border: 1px solid #d7dee8;
    border-radius: 999px;
    background: #eef1f5 !important;
    color: #526176 !important;
    font-size: 0.78rem;
    font-weight: 600;
}

.driver-notifications-page > section:nth-of-type(2) > div:nth-child(2) button {
    color: #66758b;
}

.driver-notifications-page > section:nth-of-type(2) > div:nth-child(2) button[class*='bg-white'] {
    color: #173b9c !important;
}

.driver-notifications-page > section:nth-of-type(2) > div:nth-child(2) button.text-it-primary,
.driver-notifications-page > section:nth-of-type(2) > div:nth-child(2) button[class*='text-it-primary'] {
    color: #173b9c !important;
}

:deep(#hero-section p:first-child svg) {
    width: 11px;
    height: 11px;
}

.driver-notifications-page > section:nth-of-type(2) > div:nth-child(2) button {
    min-height: 2.5rem;
    color: #50627b;
}

.driver-notifications-page > section:nth-of-type(2) > div:nth-child(2) button[class*='bg-white'] {
    color: var(--driver-blue);
    box-shadow: 0 1px 2px rgba(23, 43, 77, 0.06);
}

.driver-notifications-page > section:nth-of-type(2) > div:nth-child(3),
.driver-notifications-page > section:nth-of-type(2) > div:nth-child(4),
.driver-notifications-page > section:nth-of-type(2) > div:nth-child(5) {
    gap: 1rem;
}

.driver-notifications-page > section:nth-of-type(2) article {
    padding: 1.25rem;
    border: 1px solid #e0e7f0;
    border-radius: 0.9rem;
    background: #fff;
    box-shadow: none;
    transition: border-color 160ms ease, background-color 160ms ease;
}

.driver-notifications-page > section:nth-of-type(2) article:hover {
    border-color: #c6d3e5;
    background: #fcfdff;
}

.driver-notifications-page > section:nth-of-type(2) article button {
    color: #7889a0;
}

.driver-notifications-page > section:nth-of-type(2) article button:hover {
    color: #dc3545;
    background: #fff1f2;
}

.driver-notifications-page > section:nth-of-type(2) > div:last-child {
    min-height: 15rem;
    border: 1px solid #e0e7f0;
    border-radius: 0.95rem;
    background: #fff;
}

@media (max-width: 700px) {
    :deep(#hero-section h1) {
        font-size: 1.65rem !important;
    }

    .driver-compose-heading {
        align-items: center !important;
    }

    .driver-notifications-page form > div:last-child,
    .driver-notifications-page form :deep(button[type='submit']) {
        width: 100%;
    }

    .driver-notifications-page form :deep(button[type='submit']) {
        justify-content: center;
    }

    .driver-notifications-page form :deep(.p-select) {
        height: 48px !important;
        min-height: 48px !important;
    }

    .driver-notification-grid,
    .driver-notification-card,
    .driver-notification-card .space-y-2,
    .driver-notification-card .space-y-2 p {
        width: 100%;
    }

    .driver-notification-card .space-y-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .driver-notification-history > .driver-history-title-row {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .driver-notification-history > .driver-history-title-row > span:last-child {
        position: static;
        margin-left: 0;
    }

    .driver-notifications-page > section:nth-of-type(1) {
        padding: 1.25rem;
    }

    .driver-notifications-page > section:nth-of-type(2) > div:first-child {
        align-items: flex-start;
        flex-direction: column;
    }

    .driver-notification-history > .inline-flex {
        width: 100%;
    }

    .driver-notification-history > .inline-flex button {
        flex: 1 1 0;
        justify-content: center;
    }
}
</style>
