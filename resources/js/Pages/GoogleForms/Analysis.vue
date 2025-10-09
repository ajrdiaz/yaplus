<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Card from 'primevue/card';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Chart from 'primevue/chart';
import ProgressBar from 'primevue/progressbar';

const props = defineProps({
    survey: {
        type: Object,
        required: true
    },
    analyses: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({})
    }
});

// Filtros
const searchQuery = ref('');
const selectedCategory = ref(null);
const selectedSentiment = ref(null);

const categories = [
    { label: 'Todas', value: null },
    { label: '🆘 Necesidad', value: 'necesidad' },
    { label: '😓 Dolor', value: 'dolor' },
    { label: '✨ Sueño', value: 'sueño' },
    { label: '🚧 Objeción', value: 'objecion' },
    { label: '❓ Pregunta', value: 'pregunta' },
    { label: '👍 Experiencia Positiva', value: 'experiencia_positiva' },
    { label: '👎 Experiencia Negativa', value: 'experiencia_negativa' },
    { label: '💡 Sugerencia', value: 'sugerencia' }
];

const sentiments = [
    { label: 'Todos', value: null },
    { label: 'Positivo', value: 'positivo' },
    { label: 'Neutral', value: 'neutral' },
    { label: 'Negativo', value: 'negativo' }
];

// Análisis filtrados
const filteredAnalyses = computed(() => {
    let filtered = props.analyses;

    if (selectedCategory.value) {
        filtered = filtered.filter(a => a.category === selectedCategory.value);
    }

    if (selectedSentiment.value) {
        filtered = filtered.filter(a => a.sentiment === selectedSentiment.value);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(a => 
            a.ia_analysis?.toLowerCase().includes(query) ||
            a.response?.combined_text?.toLowerCase().includes(query)
        );
    }

    return filtered;
});

// Estadísticas por categoría
const categoryStats = computed(() => {
    if (!props.stats.by_category) return [];
    
    return Object.entries(props.stats.by_category).map(([category, count]) => ({
        category,
        count,
        label: getCategoryLabel(category),
        icon: getCategoryIcon(category),
        color: getCategoryColor(category)
    }));
});

// Estadísticas por sentimiento
const sentimentStats = computed(() => {
    if (!props.stats.by_sentiment) return [];
    
    return Object.entries(props.stats.by_sentiment).map(([sentiment, count]) => ({
        sentiment,
        count,
        label: sentiment.charAt(0).toUpperCase() + sentiment.slice(1),
        color: getSentimentColor(sentiment)
    }));
});

// Palabras clave principales
const topKeywords = computed(() => {
    if (!props.stats.top_keywords) return [];
    
    // Convertir objeto {palabra: count} a array [{word, count}]
    return Object.entries(props.stats.top_keywords)
        .map(([word, count]) => ({ word, count }))
        .slice(0, 20);
});

// Datos para gráfica de categorías
const categoryChartData = computed(() => {
    const labels = categoryStats.value.map(s => s.label);
    const data = categoryStats.value.map(s => s.count);
    const colors = categoryStats.value.map(s => s.color);
    
    return {
        labels,
        datasets: [{
            data,
            backgroundColor: colors,
            borderWidth: 0
        }]
    };
});

// Datos para gráfica de sentimientos
const sentimentChartData = computed(() => {
    const labels = sentimentStats.value.map(s => s.label);
    const data = sentimentStats.value.map(s => s.count);
    const colors = sentimentStats.value.map(s => s.color);
    
    return {
        labels,
        datasets: [{
            data,
            backgroundColor: colors,
            borderWidth: 0
        }]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom'
        }
    }
};

// Funciones helper
function getCategoryLabel(category) {
    const map = {
        'necesidad': '🆘 Necesidad',
        'dolor': '😓 Dolor',
        'sueño': '✨ Sueño',
        'objecion': '🚧 Objeción',
        'pregunta': '❓ Pregunta',
        'experiencia_positiva': '👍 Exp. Positiva',
        'experiencia_negativa': '👎 Exp. Negativa',
        'sugerencia': '💡 Sugerencia'
    };
    return map[category] || category;
}

function getCategoryIcon(category) {
    const map = {
        'necesidad': 'pi-exclamation-circle',
        'dolor': 'pi-times-circle',
        'sueño': 'pi-star',
        'objecion': 'pi-ban',
        'pregunta': 'pi-question-circle',
        'experiencia_positiva': 'pi-thumbs-up',
        'experiencia_negativa': 'pi-thumbs-down',
        'sugerencia': 'pi-lightbulb'
    };
    return map[category] || 'pi-circle';
}

function getCategoryColor(category) {
    const map = {
        'necesidad': '#FF6384',
        'dolor': '#FF9F40',
        'sueño': '#FFCD56',
        'objecion': '#4BC0C0',
        'pregunta': '#36A2EB',
        'experiencia_positiva': '#9966FF',
        'experiencia_negativa': '#C9CBCF',
        'sugerencia': '#4CAF50'
    };
    return map[category] || '#999';
}

function getSentimentColor(sentiment) {
    const map = {
        'positivo': '#4CAF50',
        'neutral': '#FFC107',
        'negativo': '#F44336'
    };
    return map[sentiment] || '#999';
}

function getSentimentSeverity(sentiment) {
    const map = {
        'positivo': 'success',
        'neutral': 'warning',
        'negativo': 'danger'
    };
    return map[sentiment] || 'info';
}

function getCategorySeverity(category) {
    const map = {
        'necesidad': 'danger',
        'dolor': 'warning',
        'sueño': 'success',
        'objecion': 'info',
        'pregunta': 'help',
        'experiencia_positiva': 'success',
        'experiencia_negativa': 'danger',
        'sugerencia': 'info'
    };
    return map[category] || 'info';
}

function goBack() {
    router.visit(route('forms.index'));
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}
</script>

<template>
    <div class="grid">
        <!-- Header -->
        <div class="col-12">
            <Card>
                <template #content>
                    <div class="flex justify-content-between align-items-center">
                        <div>
                            <Button
                                icon="pi pi-arrow-left"
                                text
                                rounded
                                @click="goBack"
                                class="mr-3"
                            />
                            <span class="text-2xl font-bold">{{ survey.title }}</span>
                        </div>
                        <div class="flex gap-2">
                            <Tag :value="`${survey.responses_count} respuestas`" severity="info" />
                            <Tag :value="`${analyses.length} analizadas`" severity="success" />
                        </div>
                    </div>
                    <div v-if="survey.description" class="mt-2 text-600">
                        {{ survey.description }}
                    </div>
                </template>
            </Card>
        </div>

        <!-- Tabs -->
        <div class="col-12">
            <Card>
                <template #content>
                    <TabView>
                        <!-- Tab 1: Resumen -->
                        <TabPanel header="Resumen">
                            <div class="grid">
                                <!-- KPIs -->
                                <div class="col-12 md:col-3">
                                    <div class="surface-card shadow-2 p-3 border-round">
                                        <div class="flex justify-content-between mb-3">
                                            <div>
                                                <span class="block text-500 font-medium mb-3">Total Analizadas</span>
                                                <div class="text-900 font-bold text-xl">{{ analyses.length }}</div>
                                            </div>
                                            <div class="flex align-items-center justify-content-center bg-blue-100 border-round" style="width:2.5rem;height:2.5rem">
                                                <i class="pi pi-chart-line text-blue-500 text-xl"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 md:col-3">
                                    <div class="surface-card shadow-2 p-3 border-round">
                                        <div class="flex justify-content-between mb-3">
                                            <div>
                                                <span class="block text-500 font-medium mb-3">Relevantes</span>
                                                <div class="text-900 font-bold text-xl">
                                                    {{ analyses.filter(a => a.is_relevant).length }}
                                                </div>
                                            </div>
                                            <div class="flex align-items-center justify-content-center bg-green-100 border-round" style="width:2.5rem;height:2.5rem">
                                                <i class="pi pi-check-circle text-green-500 text-xl"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 md:col-3">
                                    <div class="surface-card shadow-2 p-3 border-round">
                                        <div class="flex justify-content-between mb-3">
                                            <div>
                                                <span class="block text-500 font-medium mb-3">Positivos</span>
                                                <div class="text-900 font-bold text-xl">
                                                    {{ sentimentStats.find(s => s.sentiment === 'positivo')?.count || 0 }}
                                                </div>
                                            </div>
                                            <div class="flex align-items-center justify-content-center bg-green-100 border-round" style="width:2.5rem;height:2.5rem">
                                                <i class="pi pi-thumbs-up text-green-500 text-xl"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 md:col-3">
                                    <div class="surface-card shadow-2 p-3 border-round">
                                        <div class="flex justify-content-between mb-3">
                                            <div>
                                                <span class="block text-500 font-medium mb-3">Negativos</span>
                                                <div class="text-900 font-bold text-xl">
                                                    {{ sentimentStats.find(s => s.sentiment === 'negativo')?.count || 0 }}
                                                </div>
                                            </div>
                                            <div class="flex align-items-center justify-content-center bg-red-100 border-round" style="width:2.5rem;height:2.5rem">
                                                <i class="pi pi-thumbs-down text-red-500 text-xl"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Gráficas -->
                                <div class="col-12 md:col-6">
                                    <div class="surface-card shadow-2 p-3 border-round">
                                        <h3 class="text-xl font-semibold mb-3">Distribución por Categoría</h3>
                                        <Chart type="pie" :data="categoryChartData" :options="chartOptions" style="height: 300px" />
                                    </div>
                                </div>

                                <div class="col-12 md:col-6">
                                    <div class="surface-card shadow-2 p-3 border-round">
                                        <h3 class="text-xl font-semibold mb-3">Distribución por Sentimiento</h3>
                                        <Chart type="doughnut" :data="sentimentChartData" :options="chartOptions" style="height: 300px" />
                                    </div>
                                </div>

                                <!-- Top Keywords -->
                                <div class="col-12">
                                    <div class="surface-card shadow-2 p-3 border-round">
                                        <h3 class="text-xl font-semibold mb-3">Palabras Clave Principales</h3>
                                        <div class="flex flex-wrap gap-2">
                                            <Tag
                                                v-for="(keyword, index) in topKeywords"
                                                :key="index"
                                                :value="`${keyword.word} (${keyword.count})`"
                                                severity="info"
                                                rounded
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- Tab 2: Por Categoría -->
                        <TabPanel header="Por Categoría">
                            <div class="grid">
                                <div
                                    v-for="cat in categoryStats"
                                    :key="cat.category"
                                    class="col-12 md:col-6 lg:col-3"
                                >
                                    <div class="surface-card shadow-2 p-3 border-round cursor-pointer hover:surface-hover"
                                         @click="selectedCategory = cat.category"
                                    >
                                        <div class="flex justify-content-between align-items-center">
                                            <div>
                                                <span class="block text-500 font-medium mb-2">{{ cat.label }}</span>
                                                <div class="text-900 font-bold text-2xl">{{ cat.count }}</div>
                                            </div>
                                            <div class="flex align-items-center justify-content-center border-round"
                                                 :style="`background-color: ${cat.color}20; width:3rem;height:3rem`"
                                            >
                                                <i :class="`pi ${cat.icon} text-2xl`" :style="`color: ${cat.color}`"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- Tab 3: Análisis Detallado -->
                        <TabPanel header="Análisis Detallado">
                            <!-- Filtros -->
                            <div class="grid mb-3">
                                <div class="col-12 md:col-4">
                                    <InputText
                                        v-model="searchQuery"
                                        placeholder="Buscar en análisis..."
                                        class="w-full"
                                    >
                                        <template #prefix>
                                            <i class="pi pi-search" />
                                        </template>
                                    </InputText>
                                </div>
                                <div class="col-12 md:col-4">
                                    <Dropdown
                                        v-model="selectedCategory"
                                        :options="categories"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Filtrar por categoría"
                                        class="w-full"
                                    />
                                </div>
                                <div class="col-12 md:col-4">
                                    <Dropdown
                                        v-model="selectedSentiment"
                                        :options="sentiments"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Filtrar por sentimiento"
                                        class="w-full"
                                    />
                                </div>
                            </div>

                            <!-- Tabla -->
                            <DataTable
                                :value="filteredAnalyses"
                                :paginator="true"
                                :rows="10"
                                responsiveLayout="scroll"
                                class="p-datatable-sm"
                            >
                                <Column header="Respuesta" style="min-width: 300px">
                                    <template #body="{ data }">
                                        <div class="text-sm">
                                            {{ data.response?.combined_text?.substring(0, 150) }}
                                            <span v-if="data.response?.combined_text?.length > 150">...</span>
                                        </div>
                                        <small class="text-500">
                                            {{ formatDate(data.response?.submitted_at) }}
                                        </small>
                                    </template>
                                </Column>

                                <Column header="Categoría" style="min-width: 150px">
                                    <template #body="{ data }">
                                        <Tag
                                            :value="getCategoryLabel(data.category)"
                                            :severity="getCategorySeverity(data.category)"
                                        />
                                    </template>
                                </Column>

                                <Column header="Sentimiento" style="min-width: 120px">
                                    <template #body="{ data }">
                                        <Tag
                                            :value="data.sentiment"
                                            :severity="getSentimentSeverity(data.sentiment)"
                                        />
                                    </template>
                                </Column>

                                <Column header="Relevancia" style="min-width: 120px">
                                    <template #body="{ data }">
                                        <div class="flex align-items-center gap-2">
                                            <ProgressBar
                                                :value="data.relevance_score * 10"
                                                :showValue="false"
                                                style="height: 6px; width: 60px"
                                            />
                                            <span class="text-sm">{{ data.relevance_score }}/10</span>
                                        </div>
                                    </template>
                                </Column>

                                <Column header="Análisis IA" style="min-width: 400px">
                                    <template #body="{ data }">
                                        <div class="text-sm text-900">
                                            {{ data.ia_analysis }}
                                        </div>
                                        <div v-if="data.keywords && data.keywords.length > 0" class="mt-2">
                                            <Tag
                                                v-for="(keyword, index) in data.keywords.slice(0, 3)"
                                                :key="index"
                                                :value="keyword"
                                                severity="secondary"
                                                class="mr-1"
                                                rounded
                                            />
                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="text-center py-5">
                                        <i class="pi pi-inbox text-6xl text-400 mb-3"></i>
                                        <p class="text-500 text-xl">No hay análisis que coincidan con los filtros</p>
                                    </div>
                                </template>
                            </DataTable>
                        </TabPanel>

                        <!-- Tab 4: Insights -->
                        <TabPanel header="Insights Clave">
                            <div class="grid">
                                <div
                                    v-for="(analysis, index) in analyses.filter(a => a.is_relevant && a.insights && Object.keys(a.insights).length > 0)"
                                    :key="index"
                                    class="col-12 md:col-6"
                                >
                                    <div class="surface-card shadow-2 p-4 border-round">
                                        <div class="flex justify-content-between align-items-start mb-3">
                                            <Tag
                                                :value="getCategoryLabel(analysis.category)"
                                                :severity="getCategorySeverity(analysis.category)"
                                            />
                                            <Tag
                                                :value="analysis.sentiment"
                                                :severity="getSentimentSeverity(analysis.sentiment)"
                                            />
                                        </div>
                                        <p class="text-900 font-medium mb-2">{{ analysis.ia_analysis }}</p>
                                        
                                        <!-- Insights estructurados -->
                                        <div class="mt-3">
                                            <div v-if="analysis.insights.buyer_insight" class="mb-3">
                                                <div class="flex align-items-center gap-2 mb-1">
                                                    <i class="pi pi-user text-primary"></i>
                                                    <strong class="text-primary">Buyer Insight:</strong>
                                                </div>
                                                <p class="text-600 text-sm ml-4">{{ analysis.insights.buyer_insight }}</p>
                                            </div>
                                            
                                            <div v-if="analysis.insights.pain_point" class="mb-3">
                                                <div class="flex align-items-center gap-2 mb-1">
                                                    <i class="pi pi-exclamation-circle text-orange-500"></i>
                                                    <strong class="text-orange-500">Punto de Dolor:</strong>
                                                </div>
                                                <p class="text-600 text-sm ml-4">{{ analysis.insights.pain_point }}</p>
                                            </div>
                                            
                                            <div v-if="analysis.insights.opportunity" class="mb-3">
                                                <div class="flex align-items-center gap-2 mb-1">
                                                    <i class="pi pi-lightbulb text-green-500"></i>
                                                    <strong class="text-green-500">Oportunidad:</strong>
                                                </div>
                                                <p class="text-600 text-sm ml-4">{{ analysis.insights.opportunity }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="analyses.filter(a => a.is_relevant && a.insights && Object.keys(a.insights).length > 0).length === 0" class="col-12">
                                    <div class="text-center py-5">
                                        <i class="pi pi-info-circle text-6xl text-400 mb-3"></i>
                                        <p class="text-500 text-xl">No hay insights disponibles</p>
                                        <p class="text-400 text-sm">Los insights se generan automáticamente durante el análisis con IA</p>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>
                    </TabView>
                </template>
            </Card>
        </div>
    </div>
</template>

<style scoped>
.hover\:surface-hover:hover {
    background-color: var(--surface-hover);
}
</style>
