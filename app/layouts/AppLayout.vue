<script setup>
import { useLayout } from '@/layouts/composables/layout';
import { computed } from 'vue';
import AppFooter from './AppFooter.vue';
import AppSidebar from './AppSidebar.vue';
import AppTopbar from './AppTopbar.vue';
import AppMobileNav from './AppMobileNav.vue';
import ConfirmDialog from 'primevue/confirmdialog';

const { layoutConfig, layoutState, hideMobileMenu } = useLayout();
const route = useRoute();
useHead({ link: [{ rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap' }] });
const auth = useAuth();
auth.init();

const isClient = computed(() => {
    const type = auth.user.value?.type;
    return type === 'client' || type === 'cliente' || type === 3;
});

const isAdmin = computed(() => {
    const type = auth.user.value?.type;
    return type === 'admin' || type === 2 || route.path.startsWith('/dashboard/admin');
});

const isSuperAdmin = computed(() => {
    const type = auth.user.value?.type;
    return type === 'superAdmin' || type === 1 || route.path.startsWith('/dashboard/superadmin');
});

const usesProductLayout = computed(() => isClient.value || isAdmin.value || isSuperAdmin.value);

const containerClass = computed(() => {
    return {
        'layout-overlay': layoutConfig.menuMode === 'overlay',
        'layout-static': layoutConfig.menuMode === 'static',
        'layout-overlay-active': layoutState.overlayMenuActive,
        'layout-mobile-active': layoutState.mobileMenuActive,
        'layout-static-inactive': layoutState.staticMenuInactive,
        'client-sidebar-compact': usesProductLayout.value && layoutState.clientSidebarCompact
    };
});

</script>

<template>
    <div class="layout-wrapper" :class="[containerClass, { 'layout-client': usesProductLayout, 'layout-admin': isAdmin || isSuperAdmin, 'layout-superadmin': isSuperAdmin, 'user-area-refresh': isClient }]">
        <AppTopbar />
        <AppSidebar />
        <div class="layout-main-container">
            <div class="layout-main">
                <slot />
            </div>
            <AppFooter />
        </div>
        <AppMobileNav v-if="usesProductLayout" />
        <div class="layout-mask animate-fadein" @click="hideMobileMenu" />
    </div>
    <Toast />
    <ConfirmDialog :dismissable-mask="true" />
</template>

<style>
.p-confirmdialog {
    width: min(28rem, calc(100vw - 2rem));
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    box-shadow: 0 22px 55px rgba(15, 23, 42, 0.18);
    overflow: hidden;
}

.p-confirmdialog .p-dialog-header {
    padding: 1.4rem 1.5rem 1.15rem;
    border-bottom: 1px solid #e2e8f0;
}

.p-confirmdialog .p-dialog-title {
    color: #172b4d;
    font-size: 1rem;
    font-weight: 700;
}

.p-confirmdialog .p-dialog-content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 1rem 1.5rem 1.3rem;
    color: #52667f;
    line-height: 1.5;
}

.p-confirmdialog .p-confirmdialog-message {
    margin: 0;
    padding-top: 0.05rem;
}

.p-confirmdialog .p-confirmdialog-icon {
    color: #e11d48;
    flex: 0 0 auto;
    font-size: 1.25rem;
    margin-top: calc(0.1rem + 4px);
}

.p-confirmdialog .p-confirmdialog-icon.pi-check-circle {
    color: #16a34a;
}

.p-confirmdialog .p-dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.625rem;
    padding: 0 1.5rem 1.4rem;
}

.p-confirmdialog .p-button {
    min-height: 2.45rem;
    border-radius: 0.5rem;
    padding-inline: 0.9rem;
    font-size: 0.875rem;
    font-weight: 600;
}

.p-confirmdialog .p-dialog-header-actions .p-button {
    min-height: 2rem;
    padding: 0;
}

.p-confirmdialog .p-button-danger {
    background: #e11d48;
    border-color: #e11d48;
}

.p-confirmdialog .p-button-danger:hover {
    background: #be123c;
    border-color: #be123c;
}

.p-confirmdialog.driver-notification-confirm {
    width: min(25rem, calc(100vw - 2rem));
}

.p-confirmdialog.driver-notification-confirm .p-dialog-content {
    min-height: 5.75rem;
    padding: 1.35rem 1.5rem;
}

.p-confirmdialog.driver-notification-confirm .p-confirmdialog-message {
    top: 0;
    left: 0;
    color: #52667f;
}

.p-confirmdialog.driver-notification-confirm .p-dialog-footer {
    padding-top: 0.1rem;
}
</style>

<style>
.user-area-refresh{--client-brand:#2420db;--it-primary:#2420db;background:#f8f8fc;font-family:'DM Sans',Arial,sans-serif;color:#181a2b}.user-area-refresh .layout-main-container{background:#f8f8fc}.user-area-refresh .layout-topbar.client-topbar{background:#fff;border-bottom-color:#e8e8f1}.user-area-refresh .layout-sidebar{background:#fff;border-right:1px solid #e8e8f1;box-shadow:none}.user-area-refresh .layout-menu a.active-route{background:#eeedff;color:#2420db}.user-area-refresh .client-search{background:#f8f8fc!important;border:1px solid #e8e8f1}.user-area-refresh .layout-footer{color:#75788a;font-size:12px}.user-area-refresh .layout-footer-credit{display:none}.user-area-refresh .layout-main{min-width:0}.user-area-refresh .client-mobile-nav{background:#fff}.user-area-refresh :is(button,a,input,select,textarea):focus-visible{outline-color:#746eff}
</style>
