<script setup>
import { computed, watch, ref, onBeforeUnmount, onMounted } from 'vue';
import { usePrimeVue } from 'primevue/config';
import AppTopbar from './AppTopbar.vue';
import AppSidebar from './AppSidebar.vue';
import AppConfig from './AppConfig.vue';
import AppProfileSidebar from './AppProfileSidebar.vue';
import AppBreadCrumb from './AppBreadcrumb.vue';
import { useLayout } from '@/Layouts/composables/layout';

import Toast from "primevue/toast";
import ProgressSpinner from 'primevue/progressspinner';

defineProps({
    titulo: String,
    no_bordes: Boolean,
});

const $primevue = usePrimeVue();
const { spinnerLoading, layoutConfig, layoutState, isSidebarActive } = useLayout();
const outsideClickListener = ref(null);
const sidebarRef = ref(null);
const topbarRef = ref(null);

watch(isSidebarActive, (newVal) => {
    if (newVal) {
        bindOutsideClickListener();
    } else {
        unbindOutsideClickListener();
    }
});

onBeforeUnmount(() => {
    unbindOutsideClickListener();
});

const containerClass = computed(() => {
    return {
        'layout-light': layoutConfig.colorScheme.value === 'light',
        'layout-dim': layoutConfig.colorScheme.value === 'dim',
        'layout-dark': layoutConfig.colorScheme.value === 'dark',
        'layout-colorscheme-menu': layoutConfig.menuTheme.value === 'colorScheme',
        'layout-primarycolor-menu': layoutConfig.menuTheme.value === 'primaryColor',
        'layout-transparent-menu': layoutConfig.menuTheme.value === 'transparent',
        'layout-overlay': layoutConfig.menuMode.value === 'overlay',
        'layout-static': layoutConfig.menuMode.value === 'static',
        'layout-slim': layoutConfig.menuMode.value === 'slim',
        'layout-slim-plus': layoutConfig.menuMode.value === 'slim-plus',
        'layout-horizontal': layoutConfig.menuMode.value === 'horizontal',
        'layout-reveal': layoutConfig.menuMode.value === 'reveal',
        'layout-drawer': layoutConfig.menuMode.value === 'drawer',
        'layout-static-inactive': layoutState.staticMenuDesktopInactive.value && layoutConfig.menuMode.value === 'static',
        'layout-overlay-active': layoutState.overlayMenuActive.value,
        'layout-mobile-active': layoutState.staticMenuMobileActive.value,
        'p-ripple-disabled': $primevue.config.ripple === false,
        'layout-sidebar-active': layoutState.sidebarActive.value,
        'layout-sidebar-anchored': layoutState.anchored.value
    };
});

const bindOutsideClickListener = () => {
    if (!outsideClickListener.value) {
        outsideClickListener.value = (event) => {
            if (isOutsideClicked(event)) {
                layoutState.overlayMenuActive.value = false;
                layoutState.overlaySubmenuActive.value = false;
                layoutState.staticMenuMobileActive.value = false;
                layoutState.menuHoverActive.value = false;
            }
        };
        document.addEventListener('click', outsideClickListener.value);
    }
};
const unbindOutsideClickListener = () => {
    if (outsideClickListener.value) {
        document.removeEventListener('click', outsideClickListener);
        outsideClickListener.value = null;
    }
};
const isOutsideClicked = (event) => {
    const sidebarEl = sidebarRef?.value.$el;
    const topbarEl = topbarRef?.value.$el.querySelector('.topbar-menubutton');

    return !(sidebarEl.isSameNode(event.target) || sidebarEl.contains(event.target) || topbarEl.isSameNode(event.target) || topbarEl.contains(event.target));
};

const applyScale = () => {
    document.documentElement.style.fontSize = layoutConfig.scale.toString() + 'px';
};

onMounted(() => {
    layoutConfig.scale = 11
    applyScale();
});
</script>
<style>
.centered-flexbox {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    width: 100vw;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 9999;
    background-color: rgba(255, 255, 255, 0.8);
}
</style>

<template>
    <div class="centered-flexbox" v-if="spinnerLoading">
        <ProgressSpinner style="width:50px;height:50px;" />
    </div>
    <div :class="['layout-container', { ...containerClass }]">
        <AppSidebar ref="sidebarRef" />

        <div class="layout-content-wrapper h-screen flex" style="flex-direction:column">
            <AppTopbar ref="topbarRef" />
            <!-- <AppBreadCrumb class="content-breadcrumb"></AppBreadCrumb> -->
            <div class="layout-content flex-1 overflow-auto "
                :class="{ 'px-2 md:px-3 lg:px-5 py-3': !no_bordes, 'py-0 px-0': no_bordes }">
                <slot />
            </div>
        </div>

        <AppProfileSidebar />
        <!-- <AppConfig /> -->

        <Toast></Toast>
        <div class="layout-mask"></div>
    </div>

</template>
