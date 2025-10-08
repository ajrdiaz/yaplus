<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const route = usePage();
const breadcrumbRoutes = ref([]);

const setBreadcrumbRoutes = () => {
    // if (route.meta.breadcrumb) {
    //     breadcrumbRoutes.value = route.meta.breadcrumb;

    //     return;
    // }

    breadcrumbRoutes.value = usePage().url
        .split('/')
        .filter((item) => item !== '')
        .filter((item) => isNaN(Number(item)))
        .map((item) => item.charAt(0).toUpperCase() + item.slice(1));
};

const firstBreadcrumb = computed(() => {
    return breadcrumbRoutes.value.length > 0 ? breadcrumbRoutes.value[0] : null;
});

watch(
    route,
    () => {
        setBreadcrumbRoutes();
    },
    { immediate: true }
);
</script>

<template>
    <nav class="layout-breadcrumb">
        <ol>
            <template v-if="breadcrumbRoutes.length > 1">
                <li>{{ firstBreadcrumb }} </li>
                <li>>></li>
            </template>
        </ol>
    </nav>
</template>
