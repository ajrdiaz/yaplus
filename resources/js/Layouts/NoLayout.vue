<script setup>
import { computed, watch, ref, onBeforeUnmount } from 'vue';
import { usePrimeVue } from 'primevue/config';
import { useLayout } from '@/Layouts/composables/layout';

const $primevue = usePrimeVue();
const { layoutConfig, layoutState, isSidebarActive, setScale } = useLayout();
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

// valores por default
layoutConfig.menuTheme.value = 'primaryColor'; // colorScheme, primaryColor, transparent
const applyScale = () => { document.documentElement.style.fontSize = layoutConfig.scale.value + 'px'; };
layoutConfig.scale.value = 12;
setScale(layoutConfig.scale.value);
applyScale();

</script>

<template>
    <slot />
</template>