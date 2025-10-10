<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Card from 'primevue/card';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Avatar from 'primevue/avatar';
import Tag from 'primevue/tag';
import Image from 'primevue/image';
import ProgressBar from 'primevue/progressbar';
import Checkbox from 'primevue/checkbox';
import Badge from 'primevue/badge';
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    videos: {
        type: Object,
        default: () => ({ data: [], total: 0 })
    },
});

const toast = useToast();
const loading = ref(false);
const loadingComments = ref(false);
const selectedVideo = ref(null);
const videoComments = ref([]);
const activeTab = ref(0);
const expandedRows = ref([]);
const showBusinessContext = ref(false);

// Variables para edición de contexto
const showEditContextDialog = ref(false);
const editingVideo = ref(null);
const savingContext = ref(false);

// Variables para análisis IA
const loadingAnalysis = ref(false);
const analysisData = ref([]);
const analysisStats = ref(null);
const filterCategory = ref(null);
const filterSentiment = ref(null);

const form = useForm({
    video_url: '',
    max_results: 100,
    import_all: false,
    // Contexto de negocio (opcional)
    product_name: '',
    product_description: '',
    target_audience: '',
    research_goal: '',
    additional_context: '',
});

// Form para editar contexto de un video existente
const editContextForm = ref({
    product_name: '',
    product_description: '',
    target_audience: '',
    research_goal: '',
    additional_context: '',
});

const importComments = () => {
    if (!form.video_url) {
        toast.add({
            severity: 'warn',
            summary: 'Advertencia',
            detail: 'Por favor ingresa una URL de YouTube',
            life: 3000
        });
        return;
    }

    loading.value = true;

    axios.post(route('youtube.import'), {
        video_url: form.video_url,
        max_results: form.import_all ? null : form.max_results,
        import_all: form.import_all,
        // Contexto de negocio
        product_name: form.product_name,
        product_description: form.product_description,
        target_audience: form.target_audience,
        research_goal: form.research_goal,
        additional_context: form.additional_context,
    })
    .then(response => {
        if (response.data.requires_confirmation) {
            if (window.confirm(`⚠️ ${response.data.message}\n\nTiempo estimado: ${response.data.estimated_time}\n\n¿Deseas continuar?`)) {
                form.import_all = true;
                importComments();
            } else {
                loading.value = false;
            }
            return;
        }

        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Éxito',
                detail: response.data.message,
                life: 5000
            });
            
            form.reset();
            router.reload({ only: ['videos'] });
        }
    })
    .catch(error => {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Error al importar comentarios',
            life: 5000
        });
    })
    .finally(() => {
        loading.value = false;
    });
};

const loadVideoComments = (video) => {
    selectedVideo.value = video;
    loadingComments.value = true;
    activeTab.value = 1; // Cambiar a la pestaña de comentarios

    axios.get(route('youtube.video.comments', video.id))
        .then(response => {
            if (response.data.success) {
                videoComments.value = response.data.comments;
            }
        })
        .catch(error => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Error al cargar los comentarios',
                life: 3000
            });
        })
        .finally(() => {
            loadingComments.value = false;
        });
};

const deleteComment = (comment) => {
    if (!window.confirm('¿Estás seguro de eliminar este comentario?')) {
        return;
    }

    axios.delete(route('youtube.destroy', comment.id))
        .then(response => {
            if (response.data.success) {
                toast.add({
                    severity: 'success',
                    summary: 'Éxito',
                    detail: 'Comentario eliminado correctamente',
                    life: 3000
                });
                
                // Recargar comentarios del video actual
                if (selectedVideo.value) {
                    loadVideoComments(selectedVideo.value);
                }
            }
        })
        .catch(error => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Error al eliminar el comentario',
                life: 3000
            });
        });
};

const parseReplies = (repliesJson) => {
    if (!repliesJson) return [];
    try {
        return typeof repliesJson === 'string' ? JSON.parse(repliesJson) : repliesJson;
    } catch (e) {
        return [];
    }
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

const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num;
};

const getSeverity = (likeCount) => {
    if (likeCount >= 100) return 'success';
    if (likeCount >= 50) return 'info';
    if (likeCount >= 10) return 'warning';
    return 'secondary';
};

const backToVideos = () => {
    activeTab.value = 0;
    selectedVideo.value = null;
    videoComments.value = [];
};

// Funciones de Análisis IA
const analyzeVideoWithAI = (video) => {
    if (!window.confirm(`¿Analizar comentarios de "${video.title}" con IA?\n\nEsto consumirá tokens de OpenAI.`)) {
        return;
    }

    loadingAnalysis.value = true;

    axios.post(route('youtube.analyze'), {
        video_id: video.id,
        limit: null // Analizar todos
    })
    .then(response => {
        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Análisis Completado',
                detail: response.data.message,
                life: 5000
            });
            
            // Cargar análisis automáticamente
            loadAnalysis(video);
        }
    })
    .catch(error => {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Error al analizar comentarios',
            life: 5000
        });
    })
    .finally(() => {
        loadingAnalysis.value = false;
    });
};

const loadAnalysis = (video) => {
    // Navegar a la nueva vista de análisis con Inertia
    router.visit(route('youtube.video.analysis', video.id));
};

const getCategoryIcon = (category) => {
    const icons = {
        'necesidad': 'pi-lightbulb',
        'dolor': 'pi-exclamation-circle',
        'sueño': 'pi-star',
        'objecion': 'pi-times-circle',
        'pregunta': 'pi-question-circle',
        'experiencia_positiva': 'pi-thumbs-up',
        'experiencia_negativa': 'pi-thumbs-down',
        'sugerencia': 'pi-comment',
        'otro': 'pi-ellipsis-h'
    };
    return icons[category] || 'pi-circle';
};

const getCategoryColor = (category) => {
    const colors = {
        'necesidad': 'info',
        'dolor': 'danger',
        'sueño': 'success',
        'objecion': 'warning',
        'pregunta': 'help',
        'experiencia_positiva': 'success',
        'experiencia_negativa': 'danger',
        'sugerencia': 'info',
        'otro': 'secondary'
    };
    return colors[category] || 'secondary';
};

const getSentimentIcon = (sentiment) => {
    const icons = {
        'positivo': 'pi-smile',
        'negativo': 'pi-frown',
        'neutral': 'pi-minus'
    };
    return icons[sentiment] || 'pi-minus';
};

// Eliminar video completo
const deleteVideo = (video) => {
    if (!window.confirm(`¿Estás seguro de eliminar el video "${video.title}"?\n\nEsto eliminará:\n- El video\n- ${video.comments_count} comentarios\n- Todos los análisis asociados\n\nEsta acción no se puede deshacer.`)) {
        return;
    }

    axios.delete(route('youtube.video.destroy', video.id))
        .then(response => {
            if (response.data.success) {
                toast.add({
                    severity: 'success',
                    summary: 'Video Eliminado',
                    detail: response.data.message,
                    life: 5000
                });
                
                // Recargar la lista de videos
                router.reload({ only: ['videos'] });
                
                // Limpiar selección si es el video actual
                if (selectedVideo.value?.id === video.id) {
                    backToVideos();
                }
            }
        })
        .catch(error => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response?.data?.message || 'Error al eliminar el video',
                life: 5000
            });
        });
};

// Abrir modal para editar contexto de negocio
const openEditContextDialog = (video) => {
    editingVideo.value = video;
    editContextForm.value = {
        product_name: video.product_name || '',
        product_description: video.product_description || '',
        target_audience: video.target_audience || '',
        research_goal: video.research_goal || '',
        additional_context: video.additional_context || '',
    };
    showEditContextDialog.value = true;
};

// Guardar cambios del contexto de negocio
const saveBusinessContext = () => {
    if (!editingVideo.value) return;

    savingContext.value = true;

    axios.put(route('youtube.video.updateContext', editingVideo.value.id), editContextForm.value)
        .then(response => {
            if (response.data.success) {
                toast.add({
                    severity: 'success',
                    summary: 'Contexto Actualizado',
                    detail: 'El contexto de negocio se actualizó correctamente',
                    life: 3000
                });
                
                showEditContextDialog.value = false;
                router.reload({ only: ['videos'] });
            }
        })
        .catch(error => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response?.data?.message || 'Error al actualizar el contexto',
                life: 5000
            });
        })
        .finally(() => {
            savingContext.value = false;
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
                        <i class="pi pi-youtube text-4xl text-red-500"></i>
                        <span>Gestión de Comentarios de YouTube</span>
                    </div>
                </template>
                <template #content>
                    <div class="grid">
                        <div class="col-12 md:col-8">
                            <div class="p-inputgroup">
                                <span class="p-inputgroup-addon">
                                    <i class="pi pi-link"></i>
                                </span>
                                <InputText
                                    v-model="form.video_url"
                                    placeholder="https://www.youtube.com/watch?v=VIDEO_ID"
                                    :disabled="loading"
                                    @keyup.enter="importComments"
                                />
                            </div>
                            <small class="text-500">
                                Pega el enlace de un video de YouTube para importar sus comentarios
                            </small>
                        </div>
                        <div class="col-12 md:col-2">
                            <InputText
                                v-model.number="form.max_results"
                                type="number"
                                placeholder="Cantidad"
                                :disabled="loading || form.import_all"
                                min="1"
                            />
                            <small class="text-500">Límite</small>
                        </div>
                        <div class="col-12 md:col-2">
                            <Button
                                label="Importar"
                                icon="pi pi-download"
                                @click="importComments"
                                :loading="loading"
                                class="w-full"
                                severity="success"
                            />
                        </div>
                        <div class="col-12">
                            <div class="flex align-items-center">
                                <Checkbox
                                    v-model="form.import_all"
                                    :binary="true"
                                    inputId="import_all"
                                    :disabled="loading"
                                />
                                <label for="import_all" class="ml-2 cursor-pointer">
                                    <span class="font-semibold">Importar TODOS los comentarios</span>
                                    <small class="block text-500 mt-1">
                                        ⚠️ Puede tomar varios minutos si hay miles de comentarios
                                    </small>
                                </label>
                            </div>
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
                                    <!-- Campos Principales (Recomendados) -->
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
                                        <small class="text-500">
                                            <i class="pi pi-info-circle text-xs"></i>
                                            ¿Qué estás vendiendo o investigando?
                                        </small>
                                    </div>

                                    <div class="col-12">
                                        <label class="block mb-2 font-semibold">
                                            <i class="pi pi-users mr-1 text-primary"></i>
                                            Audiencia Objetivo
                                            <Tag value="Recomendado" severity="warning" class="ml-2" />
                                        </label>
                                        <Textarea
                                            v-model="form.target_audience"
                                            placeholder="Ej: Emprendedores digitales de 25-40 años, interesados en mejorar sus ventas online..."
                                            rows="2"
                                            class="w-full"
                                            :disabled="loading"
                                        />
                                        <small class="text-500">
                                            <i class="pi pi-info-circle text-xs"></i>
                                            ¿A quién está dirigido tu producto?
                                        </small>
                                    </div>

                                    <div class="col-12">
                                        <label class="block mb-2 font-semibold">
                                            <i class="pi pi-list mr-1 text-primary"></i>
                                            Descripción del Producto
                                        </label>
                                        <Textarea
                                            v-model="form.product_description"
                                            placeholder="Ej: Curso online de 8 semanas para aprender marketing digital desde cero, incluye casos prácticos y certificación..."
                                            rows="2"
                                            class="w-full"
                                            :disabled="loading"
                                        />
                                        <small class="text-500">
                                            <i class="pi pi-info-circle text-xs"></i>
                                            Breve descripción de tu producto/servicio
                                        </small>
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
                                            placeholder="Ej: Identificar principales objeciones para mejorar mi landing page"
                                            rows="2"
                                            class="w-full"
                                            :disabled="loading"
                                        />
                                        <small class="text-500">
                                            <i class="pi pi-info-circle text-xs"></i>
                                            Si tienes un objetivo específico, agrégalo aquí
                                        </small>
                                    </div>

                                    <div class="col-12 md:col-6">
                                        <label class="block mb-2 font-semibold text-700">
                                            <i class="pi pi-info-circle mr-1"></i>
                                            Contexto Adicional
                                            <Tag value="Opcional" severity="secondary" class="ml-2" />
                                        </label>
                                        <Textarea
                                            v-model="form.additional_context"
                                            placeholder="Ej: Los comentarios son de videos de mi competencia directa"
                                            rows="2"
                                            class="w-full"
                                            :disabled="loading"
                                        />
                                        <small class="text-500">
                                            <i class="pi pi-info-circle text-xs"></i>
                                            Cualquier otro detalle relevante
                                        </small>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <div class="surface-50 border-round p-3">
                                            <div class="flex align-items-start gap-2">
                                                <i class="pi pi-lightbulb text-yellow-500 text-xl"></i>
                                                <div class="flex-1">
                                                    <div class="font-semibold mb-2 text-900">💡 ¿Por qué agregar contexto?</div>
                                                    <ul class="text-sm text-700 m-0 pl-3" style="line-height: 1.8;">
                                                        <li>
                                                            <strong>Sin contexto:</strong> Análisis genérico útil para investigación exploratoria
                                                        </li>
                                                        <li>
                                                            <strong>Con Producto + Audiencia:</strong> Insights <span class="text-primary font-semibold">10x más específicos</span> y accionables 🎯
                                                        </li>
                                                        <li>
                                                            <strong>Con Objetivo:</strong> La IA enfoca el análisis en lo que necesitas descubrir
                                                        </li>
                                                    </ul>
                                                    <div class="mt-2 p-2 bg-primary-50 border-round">
                                                        <small class="text-primary font-semibold">
                                                            <i class="pi pi-check-circle mr-1"></i>
                                                            Recomendación: Completa al menos "Producto" y "Audiencia" para mejores resultados
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <ProgressBar v-if="loading" mode="indeterminate" class="mt-3" style="height: 6px" />
                </template>
            </Card>
        </div>

        <!-- Tabs: Videos y Comentarios -->
        <div class="col-12">
            <Card>
                <template #content>
                    <TabView v-model:activeIndex="activeTab">
                        <!-- Tab 1: Videos -->
                        <TabPanel>
                            <template #header>
                                <div class="flex align-items-center gap-2">
                                    <i class="pi pi-video"></i>
                                    <span>Videos</span>
                                    <Badge :value="videos.total || 0" severity="info" />
                                </div>
                            </template>

                            <DataTable
                                :value="videos.data || []"
                                dataKey="id"
                                :rowHover="true"
                                responsiveLayout="scroll"
                                :paginator="true"
                                :rows="10"
                                class="p-datatable-sm"
                            >
                                <Column header="Video" style="min-width: 400px">
                                    <template #body="{ data }">
                                        <div class="flex gap-3">
                                            <Image
                                                :src="data.thumbnail_default"
                                                :alt="data.title"
                                                width="120"
                                                preview
                                            />
                                            <div class="flex-1">
                                                <div class="font-semibold text-900 mb-1">{{ data.title }}</div>
                                                <div class="text-sm text-600 mb-2">{{ data.channel_title }}</div>
                                                <a
                                                    :href="data.url"
                                                    target="_blank"
                                                    class="text-primary text-sm"
                                                >
                                                    <i class="pi pi-external-link text-xs"></i>
                                                    Ver en YouTube
                                                </a>
                                            </div>
                                        </div>
                                    </template>
                                </Column>

                                <Column header="Estadísticas" style="min-width: 200px">
                                    <template #body="{ data }">
                                        <div class="flex flex-column gap-2">
                                            <div class="flex align-items-center gap-2">
                                                <i class="pi pi-eye text-blue-500"></i>
                                                <span class="font-semibold">{{ formatNumber(data.view_count) }}</span>
                                                <span class="text-500 text-sm">vistas</span>
                                            </div>
                                            <div class="flex align-items-center gap-2">
                                                <i class="pi pi-thumbs-up text-green-500"></i>
                                                <span class="font-semibold">{{ formatNumber(data.like_count) }}</span>
                                                <span class="text-500 text-sm">likes</span>
                                            </div>
                                            <div class="flex align-items-center gap-2">
                                                <i class="pi pi-comments text-orange-500"></i>
                                                <span class="font-semibold">{{ data.comment_count }}</span>
                                                <span class="text-500 text-sm">comentarios</span>
                                            </div>
                                        </div>
                                    </template>
                                </Column>

                                <Column header="Importados" style="min-width: 150px">
                                    <template #body="{ data }">
                                        <Tag :value="`${data.comments_count} comentarios`" severity="success" rounded />
                                    </template>
                                </Column>

                                <Column header="Fecha" style="min-width: 150px">
                                    <template #body="{ data }">
                                        <small class="text-500">{{ formatDate(data.created_at) }}</small>
                                    </template>
                                </Column>

                                <Column header="Acciones" style="min-width: 230px">
                                    <template #body="{ data }">
                                        <div class="flex gap-2">
                                            <Button
                                                icon="pi pi-eye"
                                                size="small"
                                                @click="loadVideoComments(data)"
                                                v-tooltip.top="'Ver comentarios'"
                                            />
                                            <Button
                                                icon="pi pi-briefcase"
                                                size="small"
                                                severity="secondary"
                                                @click="openEditContextDialog(data)"
                                                v-tooltip.top="'Editar contexto de negocio'"
                                                :outlined="!data.product_name"
                                            />
                                            <Button
                                                v-if="!data.analyses_count || data.analyses_count === 0"
                                                icon="pi pi-bolt"
                                                size="small"
                                                severity="success"
                                                @click="analyzeVideoWithAI(data)"
                                                v-tooltip.top="'Analizar con IA'"
                                            />
                                            <Button
                                                icon="pi pi-chart-bar"
                                                size="small"
                                                severity="help"
                                                @click="loadAnalysis(data)"
                                                v-tooltip.top="'Ver análisis IA'"
                                                :badge="data.analyses_count > 0 ? data.analyses_count.toString() : null"
                                                badgeSeverity="success"
                                            />
                                            <Button
                                                icon="pi pi-trash"
                                                size="small"
                                                severity="danger"
                                                @click="deleteVideo(data)"
                                                v-tooltip.top="'Eliminar video y todos sus datos'"
                                            />
                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="text-center py-5">
                                        <i class="pi pi-video text-6xl text-400 mb-3"></i>
                                        <p class="text-500 text-xl">No hay videos importados</p>
                                        <p class="text-400">Importa comentarios de YouTube usando el formulario de arriba</p>
                                    </div>
                                </template>
                            </DataTable>
                        </TabPanel>

                        <!-- Tab 2: Comentarios -->
                        <TabPanel :disabled="!selectedVideo">
                            <template #header>
                                <div class="flex align-items-center gap-2">
                                    <i class="pi pi-comments"></i>
                                    <span>Comentarios</span>
                                    <Badge v-if="videoComments.length" :value="videoComments.length" />
                                </div>
                            </template>

                            <div v-if="selectedVideo" class="mb-4">
                                <div class="flex justify-content-between align-items-center mb-3">
                                    <div class="flex align-items-center gap-3">
                                        <Button
                                            icon="pi pi-arrow-left"
                                            size="small"
                                            text
                                            @click="backToVideos"
                                        />
                                        <div>
                                            <h3 class="text-xl font-semibold m-0">{{ selectedVideo.title }}</h3>
                                            <small class="text-500">{{ selectedVideo.channel_title }}</small>
                                        </div>
                                    </div>
                                    <Tag :value="`${videoComments.length} comentarios`" severity="info" />
                                </div>

                                <ProgressBar v-if="loadingComments" mode="indeterminate" style="height: 4px" />

                                <DataTable
                                    v-else
                                    :value="videoComments"
                                    v-model:expandedRows="expandedRows"
                                    dataKey="id"
                                    :rowHover="true"
                                    responsiveLayout="scroll"
                                    :paginator="true"
                                    :rows="10"
                                    class="p-datatable-sm"
                                >
                                    <Column :expander="true" style="width: 3rem" />
                                    
                                    <Column field="author" header="Autor" style="min-width: 200px">
                                        <template #body="{ data }">
                                            <div class="flex align-items-center gap-2">
                                                <Avatar
                                                    :image="data.author_image"
                                                    :label="data.author.charAt(0)"
                                                    shape="circle"
                                                />
                                                <div>
                                                    <div class="font-semibold">{{ data.author }}</div>
                                                    <small class="text-500">{{ formatDate(data.published_at) }}</small>
                                                </div>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column field="text_original" header="Comentario" style="min-width: 400px">
                                        <template #body="{ data }">
                                            <div class="line-clamp-2">
                                                {{ data.text_original }}
                                            </div>
                                        </template>
                                    </Column>

                                    <Column field="like_count" header="Me gusta" style="min-width: 120px">
                                        <template #body="{ data }">
                                            <Tag :severity="getSeverity(data.like_count)" rounded>
                                                <i class="pi pi-thumbs-up mr-1"></i>
                                                {{ data.like_count }}
                                            </Tag>
                                        </template>
                                    </Column>

                                    <Column field="reply_count" header="Respuestas" style="min-width: 120px">
                                        <template #body="{ data }">
                                            <Tag v-if="data.reply_count > 0" severity="secondary" rounded>
                                                <i class="pi pi-reply mr-1"></i>
                                                {{ data.reply_count }}
                                            </Tag>
                                            <span v-else class="text-400">-</span>
                                        </template>
                                    </Column>

                                    <Column header="Acciones" style="min-width: 100px">
                                        <template #body="{ data }">
                                            <Button
                                                icon="pi pi-trash"
                                                rounded
                                                text
                                                severity="danger"
                                                @click="deleteComment(data)"
                                                v-tooltip.top="'Eliminar'"
                                            />
                                        </template>
                                    </Column>

                                    <!-- Row Expansion Template -->
                                    <template #expansion="{ data }">
                                        <div class="p-3">
                                            <div class="mb-4">
                                                <h4 class="text-lg font-semibold mb-2 text-900">Comentario Completo</h4>
                                                <div class="surface-50 border-round p-3">
                                                    <p class="text-900 line-height-3 m-0" v-html="data.text"></p>
                                                </div>
                                            </div>

                                            <div v-if="data.reply_count > 0" class="mt-4">
                                                <h4 class="text-lg font-semibold mb-3 text-900">
                                                    <i class="pi pi-reply mr-2 text-primary"></i>
                                                    Respuestas ({{ data.reply_count }})
                                                </h4>
                                                
                                                <div 
                                                    v-for="(reply, index) in parseReplies(data.replies)" 
                                                    :key="reply.id"
                                                    class="flex gap-3 mb-3 p-3 surface-50 border-round"
                                                >
                                                    <Avatar
                                                        :image="reply.author_image"
                                                        :label="reply.author?.charAt(0)"
                                                        shape="circle"
                                                        size="large"
                                                    />
                                                    <div class="flex-1">
                                                        <div class="flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                                <div class="font-semibold text-900">{{ reply.author }}</div>
                                                                <small class="text-500">{{ formatDate(reply.published_at) }}</small>
                                                            </div>
                                                            <Tag 
                                                                v-if="reply.like_count > 0" 
                                                                :value="reply.like_count" 
                                                                severity="success" 
                                                                rounded
                                                            >
                                                                <i class="pi pi-thumbs-up mr-1"></i>
                                                                {{ reply.like_count }}
                                                            </Tag>
                                                        </div>
                                                        <div class="text-900" v-html="reply.text"></div>
                                                    </div>
                                                </div>

                                                <div v-if="parseReplies(data.replies).length === 0" class="text-center text-500 py-3">
                                                    No hay respuestas para mostrar
                                                </div>
                                            </div>

                                            <div v-else class="text-center text-500 py-4">
                                                <i class="pi pi-comments text-3xl mb-2"></i>
                                                <p class="m-0">Este comentario no tiene respuestas</p>
                                            </div>
                                        </div>
                                    </template>

                                    <template #empty>
                                        <div class="text-center py-5">
                                            <i class="pi pi-inbox text-6xl text-400 mb-3"></i>
                                            <p class="text-500 text-xl">No hay comentarios</p>
                                        </div>
                                    </template>
                                </DataTable>
                            </div>

                            <div v-else class="text-center py-6">
                                <i class="pi pi-info-circle text-6xl text-400 mb-3"></i>
                                <p class="text-500 text-xl">Selecciona un video para ver sus comentarios</p>
                            </div>
                        </TabPanel>
                    </TabView>
                </template>
            </Card>
        </div>

        <!-- Dialog para editar contexto de negocio -->
        <Dialog 
            v-model:visible="showEditContextDialog" 
            modal 
            :header="editingVideo ? `Contexto de Negocio - ${editingVideo.title}` : 'Contexto de Negocio'"
            :style="{ width: '60rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
        >
            <div class="grid">
                <!-- Campos Principales (Recomendados) -->
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
                    <small class="text-500">
                        <i class="pi pi-info-circle text-xs"></i>
                        ¿Qué estás vendiendo o investigando?
                    </small>
                </div>

                <div class="col-12">
                    <label class="block mb-2 font-semibold">
                        <i class="pi pi-users mr-1 text-primary"></i>
                        Audiencia Objetivo
                        <Tag value="Recomendado" severity="warning" class="ml-2" />
                    </label>
                    <Textarea
                        v-model="editContextForm.target_audience"
                        placeholder="Ej: Emprendedores digitales de 25-40 años, interesados en mejorar sus ventas online..."
                        rows="2"
                        class="w-full"
                    />
                    <small class="text-500">
                        <i class="pi pi-info-circle text-xs"></i>
                        ¿A quién está dirigido tu producto?
                    </small>
                </div>

                <div class="col-12">
                    <label class="block mb-2 font-semibold">
                        <i class="pi pi-list mr-1 text-primary"></i>
                        Descripción del Producto
                    </label>
                    <Textarea
                        v-model="editContextForm.product_description"
                        placeholder="Ej: Curso online de 8 semanas para aprender marketing digital desde cero, incluye casos prácticos y certificación..."
                        rows="3"
                        class="w-full"
                    />
                    <small class="text-500">
                        <i class="pi pi-info-circle text-xs"></i>
                        Breve descripción de tu producto/servicio
                    </small>
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
                        v-model="editContextForm.research_goal"
                        placeholder="Ej: Identificar principales objeciones para mejorar mi landing page"
                        rows="2"
                        class="w-full"
                    />
                    <small class="text-500">
                        <i class="pi pi-info-circle text-xs"></i>
                        Si tienes un objetivo específico, agrégalo aquí
                    </small>
                </div>

                <div class="col-12 md:col-6">
                    <label class="block mb-2 font-semibold text-700">
                        <i class="pi pi-info-circle mr-1"></i>
                        Contexto Adicional
                        <Tag value="Opcional" severity="secondary" class="ml-2" />
                    </label>
                    <Textarea
                        v-model="editContextForm.additional_context"
                        placeholder="Ej: Los comentarios son de videos de mi competencia directa"
                        rows="2"
                        class="w-full"
                    />
                    <small class="text-500">
                        <i class="pi pi-info-circle text-xs"></i>
                        Cualquier otro detalle relevante
                    </small>
                </div>

                <div class="col-12 mt-3">
                    <div class="surface-50 border-round p-3">
                        <div class="flex align-items-start gap-2">
                            <i class="pi pi-info-circle text-primary text-xl"></i>
                            <div class="flex-1">
                                <div class="font-semibold mb-1 text-primary">💡 Importante</div>
                                <p class="text-sm text-700 m-0">
                                    Los cambios en el contexto afectarán los <strong>próximos análisis con IA</strong>.
                                    Los análisis ya realizados no se actualizarán automáticamente.
                                </p>
                            </div>
                        </div>
                    </div>
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

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
