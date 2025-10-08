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
        ]
    },
]);
</script>

<template>
    <AppSubMenu :model="model" />
</template>

<style lang="scss" scoped></style>
