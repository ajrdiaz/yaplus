<script setup>
import { ref } from 'vue';

import AppSubMenu from './AppSubMenu.vue';

const model = ref([
    {
        label: 'General',
        icon: 'pi pi-home',
        items: [
            {
                label: 'Inicio',
                icon: 'pi pi-fw pi-box',
                to: '/'
            },
            {
                label: 'Mis Productos',
                icon: 'pi pi-fw pi-shopping-bag',
                to: '/products',
                route: route("products.index"),
            },
        ]
    },
    {
        label: 'Herramientas Externas',
        icon: 'pi pi-cloud',
        items: [
            {
                label: 'YouTube',
                icon: 'pi pi-fw pi-youtube',
                to: '/youtube',
                route: route("youtube.index"),
            },
            {
                label: 'Google Forms',
                icon: 'pi pi-fw pi-google',
                to: '/google-forms',
                route: route("forms.index"),
            },
        ]
    },
    {
        label: 'Marketing & AI',
        icon: 'pi pi-sparkles',
        items: [
            {
                label: 'Generador de Copy',
                icon: 'pi pi-fw pi-pen-to-square',
                to: '/copy-generator',
                route: route("copy.index"),
            },
        ]
    },
]);
</script>

<template>
    <AppSubMenu :model="model" />
</template>

<style lang="scss" scoped></style>
