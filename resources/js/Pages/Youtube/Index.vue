<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Avatar from 'primevue/avatar';
import Tag from 'primevue/tag';
import ProgressBar from 'primevue/progressbar';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    comments: Object,
});

const toast = useToast();
const loading = ref(false);
const showDialog = ref(false);
const selectedComment = ref(null);

const form = useForm({
    video_url: '',
    max_results: 50,
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
        max_results: form.max_results
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
            
            // Recargar la página para mostrar los nuevos comentarios
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

const viewComment = (comment) => {
    selectedComment.value = comment;
    showDialog.value = true;
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getSeverity = (likeCount) => {
    if (likeCount >= 100) return 'success';
    if (likeCount >= 50) return 'info';
    if (likeCount >= 10) return 'warning';
    return 'secondary';
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
                                    :disabled="loading"
                                    min="1"
                                    max="100"
                                />
                                <small class="text-500">Máx: 100</small>
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
                                <div class="text-3xl font-bold text-900">{{ comments?.total || 0 }}</div>
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
                            <Tag :value="`${comments?.total || 0} comentarios`" severity="info" />
                        </div>
                    </template>
                    <template #content>
                        <DataTable
                            :value="comments?.data || []"
                            :paginator="false"
                            dataKey="id"
                            :rowHover="true"
                            responsiveLayout="scroll"
                        >
                            <Column field="author" header="Autor" style="min-width: 200px">
                                <template #body="{ data }">
                                    <div class="flex align-items-center gap-2">
                                        <Avatar
                                            :image="data.author_image"
                                            :label="data.author.charAt(0)"
                                            shape="circle"
                                            size="large"
                                        />
                                        <div>
                                            <div class="font-semibold">{{ data.author }}</div>
                                            <small class="text-500">{{ formatDate(data.published_at) }}</small>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column field="video_title" header="Video" style="min-width: 250px">
                                <template #body="{ data }">
                                    <div>
                                        <div class="font-semibold mb-1">{{ data.video_title }}</div>
                                        <a
                                            :href="data.video_url"
                                            target="_blank"
                                            class="text-primary text-sm hover:underline"
                                        >
                                            <i class="pi pi-external-link text-xs"></i>
                                            Ver en YouTube
                                        </a>
                                    </div>
                                </template>
                            </Column>

                            <Column field="text" header="Comentario" style="min-width: 300px">
                                <template #body="{ data }">
                                    <div class="line-clamp-3">
                                        {{ data.text_original.substring(0, 150) }}
                                        {{ data.text_original.length > 150 ? '...' : '' }}
                                    </div>
                                </template>
                            </Column>

                            <Column field="like_count" header="Me gusta" style="min-width: 120px">
                                <template #body="{ data }">
                                    <Tag :value="data.like_count" :severity="getSeverity(data.like_count)" rounded>
                                        <i class="pi pi-thumbs-up mr-1"></i>
                                        {{ data.like_count }}
                                    </Tag>
                                </template>
                            </Column>

                            <Column field="reply_count" header="Respuestas" style="min-width: 120px">
                                <template #body="{ data }">
                                    <Tag :value="data.reply_count" severity="secondary" rounded>
                                        <i class="pi pi-reply mr-1"></i>
                                        {{ data.reply_count }}
                                    </Tag>
                                </template>
                            </Column>

                            <Column header="Acciones" style="min-width: 150px">
                                <template #body="{ data }">
                                    <div class="flex gap-2">
                                        <Button
                                            icon="pi pi-eye"
                                            rounded
                                            text
                                            severity="info"
                                            @click="viewComment(data)"
                                            v-tooltip.top="'Ver detalles'"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            rounded
                                            text
                                            severity="danger"
                                            @click="deleteComment(data)"
                                            v-tooltip.top="'Eliminar'"
                                        />
                                    </div>
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

        <!-- Dialog para ver comentario completo -->
        <Dialog
            v-model:visible="showDialog"
            modal
            :style="{ width: '50vw' }"
            :breakpoints="{ '960px': '75vw', '640px': '90vw' }"
        >
            <template #header>
                <div class="flex align-items-center gap-2">
                    <Avatar
                        :image="selectedComment?.author_image"
                        :label="selectedComment?.author?.charAt(0)"
                        shape="circle"
                        size="large"
                    />
                    <div>
                        <div class="font-semibold text-xl">{{ selectedComment?.author }}</div>
                        <small class="text-500">{{ formatDate(selectedComment?.published_at) }}</small>
                    </div>
                </div>
            </template>

            <div v-if="selectedComment">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold mb-2">Video</h3>
                    <p class="text-900">{{ selectedComment.video_title }}</p>
                    <a
                        :href="selectedComment.video_url"
                        target="_blank"
                        class="text-primary hover:underline"
                    >
                        <i class="pi pi-external-link text-xs"></i>
                        Ver en YouTube
                    </a>
                </div>

                <div class="mb-4">
                    <h3 class="text-lg font-semibold mb-2">Comentario</h3>
                    <p class="text-900 line-height-3" v-html="selectedComment.text"></p>
                </div>

                <div class="flex gap-4 mb-4">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-thumbs-up text-blue-500"></i>
                        <span class="font-semibold">{{ selectedComment.like_count }}</span>
                        <span class="text-500">Me gusta</span>
                    </div>
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-reply text-green-500"></i>
                        <span class="font-semibold">{{ selectedComment.reply_count }}</span>
                        <span class="text-500">Respuestas</span>
                    </div>
                </div>

                <div v-if="selectedComment.replies && selectedComment.replies.length > 0">
                    <h3 class="text-lg font-semibold mb-3">Respuestas ({{ selectedComment.replies.length }})</h3>
                    <div class="flex flex-column gap-3">
                        <div
                            v-for="reply in selectedComment.replies"
                            :key="reply.id"
                            class="border-1 surface-border border-round p-3"
                        >
                            <div class="flex align-items-start gap-2">
                                <Avatar
                                    :image="reply.author_image"
                                    :label="reply.author.charAt(0)"
                                    shape="circle"
                                />
                                <div class="flex-1">
                                    <div class="font-semibold mb-1">{{ reply.author }}</div>
                                    <div class="text-900 mb-2" v-html="reply.text"></div>
                                    <div class="flex align-items-center gap-3 text-sm text-500">
                                        <span>
                                            <i class="pi pi-thumbs-up text-xs"></i>
                                            {{ reply.like_count }}
                                        </span>
                                        <span>{{ formatDate(reply.published_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
