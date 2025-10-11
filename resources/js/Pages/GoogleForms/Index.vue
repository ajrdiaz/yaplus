<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import ProgressBar from 'primevue/progressbar';
import Dropdown from 'primevue/dropdown';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    surveys: {
        type: Object,
        default: () => ({ data: [], total: 0 })
    },
    products: {
        type: Array,
        default: () => []
    },
});

const toast = useToast();
const loading = ref(false);

const form = useForm({
    sheet_url: '',
    title: '',
    description: '',
    product_id: null,
});

const importResponses = () => {
    if (!form.sheet_url || !form.title) {
        toast.add({
            severity: 'warn',
            summary: 'Advertencia',
            detail: 'Por favor ingresa la URL de Google Sheets y el título',
            life: 3000
        });
        return;
    }

    loading.value = true;

    axios.post(route('forms.import'), {
        sheet_url: form.sheet_url,
        title: form.title,
        description: form.description,
        product_id: form.product_id,
    })
    .then(response => {
        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Éxito',
                detail: response.data.message,
                life: 5000
            });
            
            form.reset();
            router.reload({ only: ['surveys'] });
        }
    })
    .catch(error => {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Error al importar respuestas',
            life: 5000
        });
    })
    .finally(() => {
        loading.value = false;
    });
};

// Variables para análisis con IA
const analyzing = ref(false);
const analyzingSurveyId = ref(null);

const analyzeWithIA = (survey) => {
    analyzingSurveyId.value = survey.id;
    analyzing.value = true;

    toast.add({
        severity: 'info',
        summary: 'Analizando...',
        detail: `Analizando ${survey.responses_count} respuestas. Esto puede tomar varios minutos.`,
        life: 5000
    });

    axios.post(route('forms.analyze'), {
        survey_id: survey.id,
        limit: null // Analizar todas
    })
    .then(response => {
        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Análisis Completado',
                detail: response.data.message,
                life: 5000
            });
            router.reload({ only: ['surveys'] });
        }
    })
    .catch(error => {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Error al analizar respuestas',
            life: 5000
        });
    })
    .finally(() => {
        analyzing.value = false;
        analyzingSurveyId.value = null;
    });
};

const viewAnalysis = (survey) => {
    // Por ahora, redirigir a una página de análisis (la crearemos después)
    router.visit(route('forms.survey.analysis', survey.id));
};

const deleteSurvey = (survey) => {
    if (!window.confirm(`¿Estás seguro de eliminar "${survey.title}"?\n\nEsto eliminará:\n- El formulario\n- ${survey.responses_count} respuestas\n- Todos los análisis asociados\n\nEsta acción no se puede deshacer.`)) {
        return;
    }

    axios.delete(route('forms.survey.destroy', survey.id))
        .then(response => {
            if (response.data.success) {
                toast.add({
                    severity: 'success',
                    summary: 'Éxito',
                    detail: response.data.message,
                    life: 3000
                });
                router.reload({ only: ['surveys'] });
            }
        })
        .catch(error => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Error al eliminar el formulario',
                life: 3000
            });
        });
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <div class="grid">
        <!-- Formulario de importación -->
        <div class="col-12">
            <Card>
                <template #title>
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-google text-4xl text-blue-500"></i>
                        <span>Importar Respuestas de Google Forms</span>
                    </div>
                </template>
                <template #content>
                    <div class="grid">
                        <div class="col-12 md:col-6">
                            <label class="block mb-2 font-semibold">URL de Google Sheets</label>
                            <InputText
                                v-model="form.sheet_url"
                                placeholder="https://docs.google.com/spreadsheets/d/..."
                                :disabled="loading"
                                class="w-full"
                            />
                            <small class="text-500">
                                <i class="pi pi-info-circle text-xs"></i>
                                Pega la URL de la hoja donde Google Forms guarda las respuestas
                            </small>
                        </div>
                        <div class="col-12 md:col-6">
                            <label class="block mb-2 font-semibold">Título del Formulario</label>
                            <InputText
                                v-model="form.title"
                                placeholder="Ej: Encuesta de Buyer Persona 2024"
                                :disabled="loading"
                                class="w-full"
                            />
                            <small class="text-500">
                                <i class="pi pi-info-circle text-xs"></i>
                                Un nombre descriptivo para identificar este formulario
                            </small>
                        </div>
                        <div class="col-12">
                            <label class="block mb-2 font-semibold">Descripción (Opcional)</label>
                            <Textarea
                                v-model="form.description"
                                placeholder="Descripción breve del propósito de este formulario..."
                                rows="2"
                                :disabled="loading"
                                class="w-full"
                            />
                        </div>

                        <!-- Selector de Producto -->
                        <div class="col-12" v-if="products.length > 0">
                            <label class="block mb-2 font-semibold">
                                <i class="pi pi-box mr-1 text-primary"></i>
                                Seleccionar Producto <span class="text-red-500">*</span>
                            </label>
                            <Dropdown
                                v-model="form.product_id"
                                :options="products"
                                optionLabel="nombre"
                                optionValue="id"
                                placeholder="Selecciona un producto..."
                                :disabled="loading"
                                class="w-full"
                            >
                                <template #value="slotProps">
                                    <div v-if="slotProps.value" class="flex align-items-center gap-2">
                                        <i class="pi pi-box text-primary"></i>
                                        <span>{{ products.find(p => p.id === slotProps.value)?.nombre }}</span>
                                    </div>
                                    <span v-else>{{ slotProps.placeholder }}</span>
                                </template>
                                <template #option="slotProps">
                                    <span class="font-semibold">{{ slotProps.option.nombre }}</span>
                                </template>
                            </Dropdown>
                            <small class="text-500">
                                <i class="pi pi-info-circle text-xs"></i>
                                Asocia este formulario a un producto existente para mejor organización
                            </small>
                        </div>

                        <div class="col-12 mt-3">
                            <Button
                                label="Importar Respuestas"
                                icon="pi pi-download"
                                @click="importResponses"
                                :loading="loading"
                                severity="success"
                            />
                        </div>
                    </div>
                    <ProgressBar v-if="loading" mode="indeterminate" class="mt-3" style="height: 6px" />
                </template>
            </Card>
        </div>

        <!-- Tabla de formularios -->
        <div class="col-12">
            <Card>
                <template #title>
                    <div class="flex justify-content-between align-items-center">
                        <span>Formularios Importados</span>
                        <Badge :value="surveys.total || 0" severity="info" />
                    </div>
                </template>
                <template #content>
                    <DataTable
                        :value="surveys.data || []"
                        dataKey="id"
                        :rowHover="true"
                        responsiveLayout="scroll"
                        :paginator="true"
                        :rows="10"
                        class="p-datatable-sm"
                    >
                        <Column header="Formulario" style="min-width: 300px">
                            <template #body="{ data }">
                                <div>
                                    <div class="font-semibold text-900 mb-1">{{ data.title }}</div>
                                    <div class="text-sm text-600" v-if="data.description">{{ data.description }}</div>
                                    <a
                                        v-if="data.form_url"
                                        :href="data.form_url"
                                        target="_blank"
                                        class="text-primary text-sm"
                                    >
                                        <i class="pi pi-external-link text-xs"></i>
                                        Ver Hoja de Cálculo
                                    </a>
                                </div>
                            </template>
                        </Column>

                        <Column header="Respuestas" style="min-width: 150px">
                            <template #body="{ data }">
                                <Tag :value="`${data.responses_count} respuestas`" severity="success" rounded />
                            </template>
                        </Column>

                        <Column header="Fecha" style="min-width: 150px">
                            <template #body="{ data }">
                                <small class="text-500">{{ formatDate(data.created_at) }}</small>
                            </template>
                        </Column>

                        <Column header="Análisis IA" style="min-width: 150px">
                            <template #body="{ data }">
                                <div v-if="data.analyses_count > 0">
                                    <Tag :value="`${data.analyses_count} analizadas`" severity="success" rounded />
                                </div>
                                <div v-else>
                                    <Tag value="Sin analizar" severity="warning" rounded />
                                </div>
                            </template>
                        </Column>

                        <Column header="Acciones" style="min-width: 300px">
                            <template #body="{ data }">
                                <div class="flex gap-2 flex-wrap">
                                    <!-- Analizar con IA -->
                                    <Button
                                        v-if="data.analyses_count === 0"
                                        icon="pi pi-sparkles"
                                        label="Analizar con IA"
                                        size="small"
                                        severity="info"
                                        @click="analyzeWithIA(data)"
                                        :loading="analyzing && analyzingSurveyId === data.id"
                                        :disabled="analyzing || data.responses_count === 0"
                                        v-tooltip.top="data.responses_count === 0 ? 'No hay respuestas para analizar' : 'Analizar respuestas con IA'"
                                    />
                                    
                                    <!-- Ver análisis -->
                                    <Button
                                        v-if="data.analyses_count > 0"
                                        icon="pi pi-chart-bar"
                                        label="Ver Análisis"
                                        size="small"
                                        severity="success"
                                        @click="viewAnalysis(data)"
                                        v-tooltip.top="'Ver resultados del análisis'"
                                    />
                                    
                                    <!-- Eliminar -->
                                    <Button
                                        icon="pi pi-trash"
                                        size="small"
                                        severity="danger"
                                        @click="deleteSurvey(data)"
                                        v-tooltip.top="'Eliminar formulario'"
                                    />
                                </div>
                            </template>
                        </Column>

                        <template #empty>
                            <div class="text-center py-5">
                                <i class="pi pi-inbox text-6xl text-400 mb-3"></i>
                                <p class="text-500 text-xl">No hay formularios importados</p>
                                <p class="text-400">Importa respuestas de Google Forms usando el formulario de arriba</p>
                            </div>
                        </template>
                    </DataTable>
                </template>
            </Card>
        </div>
    </div>
</template>
