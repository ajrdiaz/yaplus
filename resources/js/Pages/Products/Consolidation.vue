<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import Card from "primevue/card";
import Button from "primevue/button";
import Tag from "primevue/tag";
import Message from "primevue/message";
import DataTable from "primevue/datatable";
import Column from "primevue/column";

const props = defineProps({
    product: Object,
    hasConsolidatedData: Boolean,
    isStale: Boolean,
});

// Función para volver al listado
const goBack = () => {
    router.visit(route("products.index"));
};

// Función para consolidar nuevamente
const reconsolidate = () => {
    router.post(
        route("products.consolidate", props.product.id),
        {},
        {
            onSuccess: () => {
                router.reload({
                    only: ["product", "hasConsolidatedData", "isStale"],
                });
            },
        },
    );
};

// Top 5 buyer personas para la tabla
const top5Personas = computed(() => {
    return props.product.top_5_buyer_personas || [];
});

// Función para obtener el badge de fuente
const getSourceSeverity = (source) => {
    return source === "youtube" ? "danger" : "success";
};

const getSourceLabel = (source) => {
    return source === "youtube" ? "YouTube" : "Google Forms";
};
</script>

<template>
    <div class="grid">
        <!-- Header -->
        <div class="col-12">
            <Card>
                <template #content>
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <Button
                                    icon="pi pi-arrow-left"
                                    text
                                    rounded
                                    @click="goBack"
                                    v-tooltip.top="'Volver'"
                                />
                                <h1 class="text-3xl font-bold text-gray-800">
                                    📊 Consolidación de {{ product.nombre }}
                                </h1>
                            </div>
                            <p class="text-gray-600 ml-14">
                                Vista detallada de los datos consolidados de
                                buyer personas
                            </p>
                        </div>
                        <Button
                            label="Re-consolidar"
                            icon="pi pi-refresh"
                            @click="reconsolidate"
                            severity="success"
                            class="ml-auto"
                            v-tooltip.top="'Volver a procesar los datos'"
                        />
                    </div>
                </template>
            </Card>
        </div>

        <!-- Alerta si no hay datos consolidados -->
        <Message v-if="!hasConsolidatedData" severity="warn" class="mb-4">
            Este producto no tiene datos consolidados. Haz clic en
            "Re-consolidar" para procesarlos.
        </Message>

        <!-- Alerta si los datos están desactualizados -->
        <Message
            v-if="hasConsolidatedData && isStale"
            severity="info"
            class="mb-4"
        >
            Los datos consolidados tienen más de 7 días. Se recomienda
            re-consolidar para obtener información actualizada.
        </Message>

        <div class="card card-head" v-if="hasConsolidatedData">
            <!-- Información General -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-0 mb-6">
                <div class="col-12 md:col-4">
                    <Card class="h-full">
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-users text-blue-600"></i>
                                <span class="text-lg"
                                    >Total Buyer Personas</span
                                >
                            </div>
                        </template>
                        <template #content>
                            <div class="text-center">
                                <div class="text-4xl font-bold text-blue-600">
                                    {{ product.total_buyer_personas }}
                                </div>
                                <div class="text-sm text-gray-600 mt-2">
                                    <div>
                                        <i
                                            class="pi pi-youtube text-red-600"
                                        ></i>
                                        YouTube:
                                        {{ product.total_youtube_personas }}
                                    </div>
                                    <div>
                                        <i
                                            class="pi pi-google text-green-600"
                                        ></i>
                                        Google Forms:
                                        {{ product.total_google_form_personas }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <div class="col-12 md:col-4">
                    <Card class="h-full">
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-calendar text-purple-600"></i>
                                <span class="text-lg"
                                    >Última Consolidación</span
                                >
                            </div>
                        </template>
                        <template #content>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">
                                    {{ product.ultima_consolidacion_humano }}
                                </div>
                                <div class="text-sm text-gray-600 mt-2">
                                    {{ product.ultima_consolidacion }}
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <div class="col-12 md:col-4">
                    <Card class="h-full">
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-chart-line text-green-600"></i>
                                <span class="text-lg">Top 5 Seleccionados</span>
                            </div>
                        </template>
                        <template #content>
                            <div class="text-center">
                                <div class="text-4xl font-bold text-green-600">
                                    5
                                </div>
                                <div class="text-sm text-gray-600 mt-2">
                                    Buyer personas más completos y recientes
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>

            <!-- Demografía -->
            <Card class="mb-6" v-if="product.demografia_promedio">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-chart-pie text-orange-600"></i>
                        Demografía Promedio
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-4 bg-orange-50 rounded-lg">
                            <div class="text-sm text-gray-600 mb-1">
                                Edad Promedio
                            </div>
                            <div class="text-2xl font-bold text-orange-600">
                                {{
                                    product.demografia_promedio.edad_promedio ||
                                    "N/A"
                                }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Rango:
                                {{
                                    product.demografia_promedio.edad_rango ||
                                    "N/A"
                                }}
                            </div>
                        </div>
                        <div class="p-4 bg-blue-50 rounded-lg col-span-2">
                            <div class="text-sm text-gray-600 mb-2">
                                Ocupaciones Principales
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Tag
                                    v-for="(ocupacion, idx) in product
                                        .demografia_promedio
                                        .ocupaciones_principales"
                                    :key="idx"
                                    :value="ocupacion"
                                    severity="info"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Top 5 Buyer Personas -->
            <Card class="mb-6">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-star-fill text-yellow-500"></i>
                        Top 5 Buyer Personas
                    </div>
                </template>
                <template #content>
                    <DataTable :value="top5Personas" stripedRows>
                        <template #empty>
                            <div class="text-center py-4 text-gray-500">
                                No hay buyer personas seleccionados
                            </div>
                        </template>

                        <Column
                            field="nombre"
                            header="Nombre"
                            style="width: 15%"
                        >
                            <template #body="{ data }">
                                <div class="font-semibold">
                                    {{ data.nombre }}
                                </div>
                            </template>
                        </Column>

                        <Column
                            field="source"
                            header="Fuente"
                            style="width: 12%"
                        >
                            <template #body="{ data }">
                                <Tag
                                    :value="getSourceLabel(data.source)"
                                    :severity="getSourceSeverity(data.source)"
                                />
                            </template>
                        </Column>

                        <Column
                            field="edad"
                            header="Edad"
                            style="width: 8%"
                        ></Column>
                        <Column
                            field="ocupacion"
                            header="Ocupación"
                            style="width: 15%"
                        ></Column>

                        <Column
                            field="pain_points"
                            header="Pain Points"
                            style="width: 20%"
                        >
                            <template #body="{ data }">
                                <div class="text-sm">
                                    {{
                                        data.pain_points?.slice(0, 2).join(", ")
                                    }}
                                    <span
                                        v-if="data.pain_points?.length > 2"
                                        class="text-gray-400"
                                    >
                                        +{{ data.pain_points.length - 2 }} más
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <Column
                            field="motivaciones"
                            header="Motivaciones"
                            style="width: 20%"
                        >
                            <template #body="{ data }">
                                <div class="text-sm">
                                    {{
                                        data.motivaciones
                                            ?.slice(0, 2)
                                            .join(", ")
                                    }}
                                    <span
                                        v-if="data.motivaciones?.length > 2"
                                        class="text-gray-400"
                                    >
                                        +{{ data.motivaciones.length - 2 }} más
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <Column
                            field="source_name"
                            header="Origen"
                            style="width: 10%"
                        >
                            <template #body="{ data }">
                                <div class="text-xs text-gray-600">
                                    {{ data.source_name }}
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>

            <!-- Pain Points Consolidados -->
            <Card class="mb-6" v-if="product.pain_points_consolidados?.length">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-exclamation-circle text-red-600"></i>
                        Pain Points Consolidados (Top 15)
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="(
                                item, idx
                            ) in product.pain_points_consolidados"
                            :key="idx"
                            class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200"
                        >
                            <div class="flex items-start gap-2 flex-1">
                                <span class="font-bold text-red-600"
                                    >{{ idx + 1 }}.</span
                                >
                                <span class="text-gray-800">{{
                                    item.texto
                                }}</span>
                            </div>
                            <Tag
                                :value="`${item.frecuencia}x`"
                                severity="danger"
                            />
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Motivaciones Consolidadas -->
            <Card class="mb-6" v-if="product.motivaciones_consolidadas?.length">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-bolt text-blue-600"></i>
                        Motivaciones Consolidadas (Top 15)
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="(
                                item, idx
                            ) in product.motivaciones_consolidadas"
                            :key="idx"
                            class="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-200"
                        >
                            <div class="flex items-start gap-2 flex-1">
                                <span class="font-bold text-blue-600"
                                    >{{ idx + 1 }}.</span
                                >
                                <span class="text-gray-800">{{
                                    item.texto
                                }}</span>
                            </div>
                            <Tag
                                :value="`${item.frecuencia}x`"
                                severity="info"
                            />
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Sueños/Aspiraciones -->
            <Card class="mb-6" v-if="product.suenos_consolidados?.length">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-star text-yellow-600"></i>
                        Sueños y Aspiraciones (Top 15)
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="(item, idx) in product.suenos_consolidados"
                            :key="idx"
                            class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg border border-yellow-200"
                        >
                            <div class="flex items-start gap-2 flex-1">
                                <span class="font-bold text-yellow-600"
                                    >{{ idx + 1 }}.</span
                                >
                                <span class="text-gray-800">{{
                                    item.texto
                                }}</span>
                            </div>
                            <Tag
                                :value="`${item.frecuencia}x`"
                                severity="warning"
                            />
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Objeciones -->
            <Card class="mb-6" v-if="product.objeciones_consolidadas?.length">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-times-circle text-orange-600"></i>
                        Objeciones Comunes (Top 15)
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="(
                                item, idx
                            ) in product.objeciones_consolidadas"
                            :key="idx"
                            class="flex items-center justify-between p-3 bg-orange-50 rounded-lg border border-orange-200"
                        >
                            <div class="flex items-start gap-2 flex-1">
                                <span class="font-bold text-orange-600"
                                    >{{ idx + 1 }}.</span
                                >
                                <span class="text-gray-800">{{
                                    item.texto
                                }}</span>
                            </div>
                            <Tag
                                :value="`${item.frecuencia}x`"
                                :style="{
                                    background: '#f97316',
                                    color: 'white',
                                }"
                            />
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Keywords -->
            <Card class="mb-6" v-if="product.keywords_consolidadas?.length">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-key text-purple-600"></i>
                        Keywords Más Usadas (Top 20)
                    </div>
                </template>
                <template #content>
                    <div class="flex flex-wrap gap-2">
                        <Tag
                            v-for="(item, idx) in product.keywords_consolidadas"
                            :key="idx"
                            :value="`${item.texto} (${item.frecuencia}x)`"
                            severity="secondary"
                            :style="{
                                fontSize: `${Math.min(
                                    1.5,
                                    0.8 + item.frecuencia / 10,
                                )}rem`,
                                padding: '0.5rem 1rem',
                            }"
                        />
                    </div>
                </template>
            </Card>

            <!-- Canales Preferidos -->
            <Card class="mb-6" v-if="product.canales_preferidos?.length">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-share-alt text-green-600"></i>
                        Canales Preferidos
                    </div>
                </template>
                <template #content>
                    <div class="flex flex-wrap gap-3">
                        <div
                            v-for="(item, idx) in product.canales_preferidos"
                            :key="idx"
                            class="p-3 bg-green-50 rounded-lg border border-green-200"
                        >
                            <div class="font-semibold text-green-700">
                                {{ item.texto }}
                            </div>
                            <div class="text-xs text-gray-600 mt-1">
                                {{ item.frecuencia }} menciones
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Insights -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Card v-if="product.insights_youtube">
                    <template #title>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-youtube text-red-600"></i>
                            Insights de YouTube
                        </div>
                    </template>
                    <template #content>
                        <p class="text-gray-700 leading-relaxed">
                            {{ product.insights_youtube }}
                        </p>
                    </template>
                </Card>

                <Card v-if="product.insights_google_forms">
                    <template #title>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-google text-green-600"></i>
                            Insights de Google Forms
                        </div>
                    </template>
                    <template #content>
                        <p class="text-gray-700 leading-relaxed">
                            {{ product.insights_google_forms }}
                        </p>
                    </template>
                </Card>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Estilos adicionales si son necesarios */
</style>
