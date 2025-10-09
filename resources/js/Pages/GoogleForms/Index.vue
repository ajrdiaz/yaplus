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
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    surveys: {
        type: Object,
        default: () => ({ data: [], total: 0 })
    },
});

const toast = useToast();
const loading = ref(false);
const showBusinessContext = ref(false);

// Variables para edición de contexto
const showEditContextDialog = ref(false);
const editingSurvey = ref(null);
const savingContext = ref(false);

const form = useForm({
    sheet_url: '',
    title: '',
    description: '',
    // Contexto de negocio (opcional)
    product_name: '',
    product_description: '',
    target_audience: '',
    research_goal: '',
    additional_context: '',
});

// Form para editar contexto
const editContextForm = ref({
    product_name: '',
    product_description: '',
    target_audience: '',
    research_goal: '',
    additional_context: '',
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
        product_name: form.product_name,
        product_description: form.product_description,
        target_audience: form.target_audience,
        research_goal: form.research_goal,
        additional_context: form.additional_context,
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

const openEditContextDialog = (survey) => {
    editingSurvey.value = survey;
    editContextForm.value = {
        product_name: survey.product_name || '',
        product_description: survey.product_description || '',
        target_audience: survey.target_audience || '',
        research_goal: survey.research_goal || '',
        additional_context: survey.additional_context || '',
    };
    showEditContextDialog.value = true;
};

const saveBusinessContext = () => {
    if (!editingSurvey.value) return;

    savingContext.value = true;

    axios.put(route('forms.survey.updateContext', editingSurvey.value.id), editContextForm.value)
        .then(response => {
            if (response.data.success) {
                toast.add({
                    severity: 'success',
                    summary: 'Éxito',
                    detail: 'Contexto actualizado correctamente',
                    life: 3000
                });
                showEditContextDialog.value = false;
                router.reload({ only: ['surveys'] });
            }
        })
        .catch(error => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Error al actualizar el contexto',
                life: 3000
            });
        })
        .finally(() => {
            savingContext.value = false;
        });
};

// Variables para análisis con IA
const analyzing = ref(false);
const analyzingSurveyId = ref(null);

const analyzeWithIA = (survey) => {
    if (!survey.product_name && !confirm('No has configurado el contexto de negocio.\n\nEl análisis será más preciso si configuras:\n- Nombre del producto\n- Audiencia objetivo\n\n¿Deseas continuar sin contexto?')) {
        return;
    }

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

                        <!-- Contexto de Negocio (Opcional) -->
                        <div class="col-12 mt-3">
                            <div class="surface-100 border-round p-3">
                                <div 
                                    class="flex align-items-center justify-content-between cursor-pointer"
                                    @click="showBusinessContext = !showBusinessContext"
                                >
                                    <div class="flex align-items-center gap-2">
                                        <i class="pi pi-briefcase text-primary text-xl"></i>
                                        <span class="font-semibold text-primary">
                                            Contexto de Negocio (Opcional - Mejora el análisis con IA)
                                        </span>
                                    </div>
                                    <i :class="showBusinessContext ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"></i>
                                </div>
                                
                                <div v-show="showBusinessContext" class="mt-3 grid">
                                    <!-- Campos Principales -->
                                    <div class="col-12">
                                        <div class="flex align-items-center gap-2 mb-3">
                                            <i class="pi pi-star-fill text-yellow-500"></i>
                                            <span class="font-semibold text-900">Información Principal (Recomendada)</span>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="block mb-2 font-semibold">
                                            <i class="pi pi-shopping-bag mr-1 text-primary"></i>
                                            Nombre del Producto/Servicio
                                            <Tag value="Recomendado" severity="warning" class="ml-2" />
                                        </label>
                                        <InputText
                                            v-model="form.product_name"
                                            placeholder="Ej: Curso de Marketing Digital"
                                            class="w-full"
                                            :disabled="loading"
                                        />
                                    </div>

                                    <div class="col-12">
                                        <label class="block mb-2 font-semibold">
                                            <i class="pi pi-users mr-1 text-primary"></i>
                                            Audiencia Objetivo
                                            <Tag value="Recomendado" severity="warning" class="ml-2" />
                                        </label>
                                        <Textarea
                                            v-model="form.target_audience"
                                            placeholder="Ej: Emprendedores digitales de 25-40 años..."
                                            rows="2"
                                            class="w-full"
                                            :disabled="loading"
                                        />
                                    </div>

                                    <div class="col-12">
                                        <label class="block mb-2 font-semibold">
                                            <i class="pi pi-list mr-1 text-primary"></i>
                                            Descripción del Producto
                                        </label>
                                        <Textarea
                                            v-model="form.product_description"
                                            placeholder="Ej: Curso online de 8 semanas..."
                                            rows="2"
                                            class="w-full"
                                            :disabled="loading"
                                        />
                                    </div>

                                    <!-- Divisor -->
                                    <div class="col-12 my-2">
                                        <hr class="border-300" />
                                    </div>

                                    <!-- Campos Opcionales -->
                                    <div class="col-12">
                                        <div class="flex align-items-center gap-2 mb-3">
                                            <i class="pi pi-ellipsis-h text-500"></i>
                                            <span class="font-semibold text-700">Información Adicional (Opcional)</span>
                                        </div>
                                    </div>

                                    <div class="col-12 md:col-6">
                                        <label class="block mb-2 font-semibold text-700">
                                            <i class="pi pi-flag mr-1"></i>
                                            Objetivo de Investigación
                                            <Tag value="Opcional" severity="secondary" class="ml-2" />
                                        </label>
                                        <Textarea
                                            v-model="form.research_goal"
                                            placeholder="Ej: Identificar principales objeciones..."
                                            rows="2"
                                            class="w-full"
                                            :disabled="loading"
                                        />
                                    </div>

                                    <div class="col-12 md:col-6">
                                        <label class="block mb-2 font-semibold text-700">
                                            <i class="pi pi-info-circle mr-1"></i>
                                            Contexto Adicional
                                            <Tag value="Opcional" severity="secondary" class="ml-2" />
                                        </label>
                                        <Textarea
                                            v-model="form.additional_context"
                                            placeholder="Ej: Encuesta enviada a clientes actuales"
                                            rows="2"
                                            class="w-full"
                                            :disabled="loading"
                                        />
                                    </div>
                                </div>
                            </div>
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
                                    
                                    <!-- Editar contexto -->
                                    <Button
                                        icon="pi pi-briefcase"
                                        size="small"
                                        severity="secondary"
                                        @click="openEditContextDialog(data)"
                                        v-tooltip.top="'Editar contexto de negocio'"
                                        :outlined="!data.product_name"
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

        <!-- Dialog para editar contexto -->
        <Dialog 
            v-model:visible="showEditContextDialog" 
            modal 
            :header="editingSurvey ? `Contexto de Negocio - ${editingSurvey.title}` : 'Contexto de Negocio'"
            :style="{ width: '60rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
        >
            <div class="grid">
                <div class="col-12">
                    <div class="flex align-items-center gap-2 mb-3">
                        <i class="pi pi-star-fill text-yellow-500"></i>
                        <span class="font-semibold text-900">Información Principal (Recomendada)</span>
                    </div>
                </div>

                <div class="col-12">
                    <label class="block mb-2 font-semibold">
                        <i class="pi pi-shopping-bag mr-1 text-primary"></i>
                        Nombre del Producto/Servicio
                        <Tag value="Recomendado" severity="warning" class="ml-2" />
                    </label>
                    <InputText
                        v-model="editContextForm.product_name"
                        placeholder="Ej: Curso de Marketing Digital"
                        class="w-full"
                    />
                </div>

                <div class="col-12">
                    <label class="block mb-2 font-semibold">
                        <i class="pi pi-users mr-1 text-primary"></i>
                        Audiencia Objetivo
                        <Tag value="Recomendado" severity="warning" class="ml-2" />
                    </label>
                    <Textarea
                        v-model="editContextForm.target_audience"
                        placeholder="Ej: Emprendedores digitales de 25-40 años..."
                        rows="2"
                        class="w-full"
                    />
                </div>

                <div class="col-12">
                    <label class="block mb-2 font-semibold">
                        <i class="pi pi-list mr-1 text-primary"></i>
                        Descripción del Producto
                    </label>
                    <Textarea
                        v-model="editContextForm.product_description"
                        placeholder="Ej: Curso online de 8 semanas..."
                        rows="3"
                        class="w-full"
                    />
                </div>

                <div class="col-12 my-2">
                    <hr class="border-300" />
                </div>

                <div class="col-12">
                    <div class="flex align-items-center gap-2 mb-3">
                        <i class="pi pi-ellipsis-h text-500"></i>
                        <span class="font-semibold text-700">Información Adicional (Opcional)</span>
                    </div>
                </div>

                <div class="col-12 md:col-6">
                    <label class="block mb-2 font-semibold text-700">
                        <i class="pi pi-flag mr-1"></i>
                        Objetivo de Investigación
                        <Tag value="Opcional" severity="secondary" class="ml-2" />
                    </label>
                    <Textarea
                        v-model="editContextForm.research_goal"
                        placeholder="Ej: Identificar principales objeciones..."
                        rows="2"
                        class="w-full"
                    />
                </div>

                <div class="col-12 md:col-6">
                    <label class="block mb-2 font-semibold text-700">
                        <i class="pi pi-info-circle mr-1"></i>
                        Contexto Adicional
                        <Tag value="Opcional" severity="secondary" class="ml-2" />
                    </label>
                    <Textarea
                        v-model="editContextForm.additional_context"
                        placeholder="Ej: Encuesta enviada a clientes actuales"
                        rows="2"
                        class="w-full"
                    />
                </div>
            </div>

            <template #footer>
                <Button 
                    label="Cancelar" 
                    icon="pi pi-times" 
                    text 
                    @click="showEditContextDialog = false" 
                    :disabled="savingContext"
                />
                <Button 
                    label="Guardar Cambios" 
                    icon="pi pi-check" 
                    @click="saveBusinessContext" 
                    :loading="savingContext"
                />
            </template>
        </Dialog>
    </div>
</template>
