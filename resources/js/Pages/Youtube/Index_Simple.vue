<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import ProgressBar from 'primevue/progressbar';
import Checkbox from 'primevue/checkbox';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    comments: {
        type: Object,
        default: () => ({ data: [], total: 0 })
    },
});

const toast = useToast();
const loading = ref(false);

const form = useForm({
    video_url: '',
    max_results: 100,
    import_all: false,
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
        import_all: form.import_all
    })
    .then(response => {
        // Si requiere confirmación (muchos comentarios)
        if (response.data.requires_confirmation) {
            if (confirm(`⚠️ ${response.data.message}\n\nTiempo estimado: ${response.data.estimated_time}\n\n¿Deseas continuar?`)) {
                // Reintentar con confirmación
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
            router.reload({ only: ['comments'] });
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

const deleteComment = (comment) => {
    if (!confirm('¿Estás seguro de eliminar este comentario?')) {
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
                router.reload({ only: ['comments'] });
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

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
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
                            <span>Importar Comentarios de YouTube</span>
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
                                <small class="text-500">Límite personalizado</small>
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
                                        <span class="font-semibold">Importar TODOS los comentarios del video</span>
                                        <small class="block text-500 mt-1">
                                            ⚠️ Esto puede tomar varios minutos si el video tiene miles de comentarios
                                        </small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <ProgressBar v-if="loading" mode="indeterminate" class="mt-3" style="height: 6px" />
                    </template>
                </Card>
            </div>

            <!-- Estadísticas rápidas -->
            <div class="col-12 md:col-3">
                <Card>
                    <template #content>
                        <div class="flex align-items-center justify-content-between">
                            <div>
                                <div class="text-500 font-medium mb-2">Total Comentarios</div>
                                <div class="text-3xl font-bold text-900">{{ comments.total || 0 }}</div>
                            </div>
                            <div class="bg-blue-100 text-blue-600 border-circle p-3">
                                <i class="pi pi-comments text-2xl"></i>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Tabla de comentarios -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="flex justify-content-between align-items-center">
                            <span>Comentarios Importados</span>
                            <Tag :value="`${comments.total || 0} comentarios`" severity="info" />
                        </div>
                    </template>
                    <template #content>
                        <DataTable
                            :value="comments.data || []"
                            dataKey="id"
                            :rowHover="true"
                            responsiveLayout="scroll"
                            :paginator="true"
                            :rows="10"
                        >
                            <Column field="author" header="Autor" style="min-width: 200px">
                                <template #body="{ data }">
                                    <div>
                                        <div class="font-semibold">{{ data.author }}</div>
                                        <small class="text-500">{{ formatDate(data.published_at) }}</small>
                                    </div>
                                </template>
                            </Column>

                            <Column field="video.title" header="Video" style="min-width: 250px">
                                <template #body="{ data }">
                                    <div>
                                        <div class="font-semibold mb-1">{{ data.video?.title || 'Sin título' }}</div>
                                        <a
                                            :href="data.video?.url"
                                            target="_blank"
                                            class="text-primary text-sm"
                                            v-if="data.video?.url"
                                        >
                                            <i class="pi pi-external-link text-xs"></i>
                                            Ver en YouTube
                                        </a>
                                    </div>
                                </template>
                            </Column>

                            <Column field="text_original" header="Comentario" style="min-width: 300px">
                                <template #body="{ data }">
                                    <div>
                                        {{ data.text_original.substring(0, 100) }}
                                        {{ data.text_original.length > 100 ? '...' : '' }}
                                    </div>
                                </template>
                            </Column>

                            <Column field="like_count" header="Me gusta" style="min-width: 100px">
                                <template #body="{ data }">
                                    <Tag severity="success" rounded>
                                        <i class="pi pi-thumbs-up mr-1"></i>
                                        {{ data.like_count }}
                                    </Tag>
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
                                    />
                                </template>
                            </Column>

                            <template #empty>
                                <div class="text-center py-5">
                                    <i class="pi pi-inbox text-6xl text-400 mb-3"></i>
                                    <p class="text-500 text-xl">No hay comentarios importados</p>
                                    <p class="text-400">Importa comentarios de YouTube usando el formulario de arriba</p>
                                </div>
                            </template>
                        </DataTable>
                    </template>
                </Card>
            </div>
        </div>
</template>
