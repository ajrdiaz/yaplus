<script setup>
import Card from 'primevue/card';

defineProps({
    comments: Object
});
</script>

<template>
    <Card>
            <template #title>
                YouTube Comments Test
            </template>
            <template #content>
                <div>
                    <h2>La página funciona!</h2>
                    <p>Total de comentarios: {{ comments?.total || 0 }}</p>
                    <pre>{{ JSON.stringify(comments, null, 2) }}</pre>
                </div>
            </template>
        </Card>
</template>
