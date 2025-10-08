<script setup>
import { Link } from '@inertiajs/vue3';
import { useLayout } from '@/Layouts/composables/layout';
import AppMenu from './AppMenu.vue';
// import Button from "primevue/button";
import AppLogo from '@/Components/Images/Logo.vue';

const { layoutState } = useLayout();

let timeout = null;

const onMouseEnter = () => {
    if (!layoutState.anchored.value) {
        if (timeout) {
            clearTimeout(timeout);
            timeout = null;
        }
        layoutState.sidebarActive.value = true;
    }
};

const onMouseLeave = () => {
    if (!layoutState.anchored.value) {
        if (!timeout) {
            timeout = setTimeout(() => (layoutState.sidebarActive.value = false), 300);
        }
    }
};

const anchor = () => {
    layoutState.anchored.value = !layoutState.anchored.value;
};

</script>
<script>
export default {
    components: {
        AppLogo
    },
    data() {
        return {
            fillColor: 'var(--logo-color)'
        }
    },
}
</script>

<template>
    <div class="layout-sidebar" @mouseenter="onMouseEnter" @mouseleave="onMouseLeave">
        <div class="sidebar-header">
            <Link :href="route('inicio')" class="app-logo">
                <AppLogo :fill="fillColor" />
            </Link>
            <button class="layout-sidebar-anchor p-link z-2 mb-2" type="button" @click="anchor()"></button>
        </div>
        <div class="layout-menu-container">
            <AppMenu />
        </div>
    </div>
</template>

<style lang="scss" scoped></style>
