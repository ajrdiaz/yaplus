<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';

const props = defineProps({
    products: Array,
});

// Estado del formulario
const showDialog = ref(false);
const isEditing = ref(false);
const formData = ref({
    id: null,
    nombre: '',
    audiencia_objetivo: '',
    descripcion: '',
});

const errorMessage = ref('');
const successMessage = ref('');

// Abrir dialog para crear
const openCreateDialog = () => {
    isEditing.value = false;
    formData.value = {
        id: null,
        nombre: '',
        audiencia_objetivo: '',
        descripcion: '',
    };
    errorMessage.value = '';
    showDialog.value = true;
};

// Abrir dialog para editar
const openEditDialog = (product) => {
    isEditing.value = true;
    formData.value = {
        id: product.id,
        nombre: product.nombre,
        audiencia_objetivo: product.audiencia_objetivo || '',
        descripcion: product.descripcion || '',
    };
    errorMessage.value = '';
    showDialog.value = true;
};

// Guardar producto (crear o actualizar)
const saveProduct = async () => {
    if (!formData.value.nombre.trim()) {
        errorMessage.value = 'El nombre del producto es requerido';
        return;
    }

    try {
        if (isEditing.value) {
            // Actualizar
            await axios.put(route('products.update', formData.value.id), {
                nombre: formData.value.nombre,
                audiencia_objetivo: formData.value.audiencia_objetivo,
                descripcion: formData.value.descripcion,
            });
            successMessage.value = 'Producto actualizado exitosamente';
        } else {
            // Crear
            await axios.post(route('products.store'), {
                nombre: formData.value.nombre,
                audiencia_objetivo: formData.value.audiencia_objetivo,
                descripcion: formData.value.descripcion,
            });
            successMessage.value = 'Producto creado exitosamente';
        }

        showDialog.value = false;
        router.reload({ only: ['products'] });

        // Limpiar mensaje después de 3 segundos
        setTimeout(() => {
            successMessage.value = '';
        }, 3000);
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Error al guardar el producto';
    }
};

// Eliminar producto
const deleteProduct = async (product) => {
    if (!confirm(`¿Estás seguro de eliminar el producto "${product.nombre}"?`)) {
        return;
    }

    try {
        await axios.delete(route('products.destroy', product.id));
        successMessage.value = 'Producto eliminado exitosamente';
        router.reload({ only: ['products'] });

        setTimeout(() => {
            successMessage.value = '';
        }, 3000);
    } catch (error) {
        errorMessage.value = 'Error al eliminar el producto';
    }
};

// Cerrar dialog
const closeDialog = () => {
    showDialog.value = false;
    errorMessage.value = '';
};
</script>

<template>
    <div class="p-4">
        <!-- Header -->
        <div class="flex items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    📦 Mis Productos
                </h1>
                <p class="text-gray-600">
                    Gestiona los productos que trabajarás con tus análisis de YouTube y Google Forms
                </p>
            </div>
            <Button
                label="Nuevo Producto"
                icon="pi pi-plus"
                @click="openCreateDialog"
                severity="success"
                class="ml-auto"
            />
        </div>

            <!-- Mensaje de éxito -->
            <Message v-if="successMessage" severity="success" :closable="true" @close="successMessage = ''">
                {{ successMessage }}
            </Message>

            <!-- Tabla de productos -->
            <DataTable
                :value="products"
                stripedRows
                paginator
                :rows="10"
                :rowsPerPageOptions="[5, 10, 20, 50]"
                tableStyle="min-width: 50rem"
                class="mt-4"
            >
                <template #empty>
                    <div class="text-center py-8">
                        <i class="pi pi-inbox text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">No hay productos registrados</p>
                        <p class="text-gray-400 text-sm mt-2">Haz clic en "Nuevo Producto" para comenzar</p>
                    </div>
                </template>

                <Column field="nombre" header="Nombre del Producto" sortable>
                    <template #body="{ data }">
                        <div class="font-semibold text-gray-800">
                            {{ data.nombre }}
                        </div>
                    </template>
                </Column>

                <Column field="audiencia_objetivo" header="Audiencia Objetivo" sortable>
                    <template #body="{ data }">
                        <div class="text-gray-600">
                            {{ data.audiencia_objetivo || '-' }}
                        </div>
                    </template>
                </Column>

                <Column field="descripcion" header="Descripción">
                    <template #body="{ data }">
                        <div class="text-gray-600 max-w-md truncate" v-tooltip.top="data.descripcion">
                            {{ data.descripcion || '-' }}
                        </div>
                    </template>
                </Column>

                <Column field="created_at" header="Fecha de Creación" sortable>
                    <template #body="{ data }">
                        <div class="text-gray-500 text-sm">
                            {{ new Date(data.created_at).toLocaleDateString('es-ES') }}
                        </div>
                    </template>
                </Column>

                <Column header="Acciones" :exportable="false" style="width: 150px">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button
                                icon="pi pi-pencil"
                                text
                                rounded
                                severity="info"
                                @click="openEditDialog(data)"
                                v-tooltip.top="'Editar'"
                            />
                            <Button
                                icon="pi pi-trash"
                                text
                                rounded
                                severity="danger"
                                @click="deleteProduct(data)"
                                v-tooltip.top="'Eliminar'"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <!-- Dialog para crear/editar -->
            <Dialog
                v-model:visible="showDialog"
                :header="isEditing ? 'Editar Producto' : 'Nuevo Producto'"
                :modal="true"
                :closable="true"
                :style="{ width: '600px' }"
                @hide="closeDialog"
            >
                <div class="space-y-4">
                    <!-- Nombre del producto -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre del Producto *
                        </label>
                        <InputText
                            v-model="formData.nombre"
                            placeholder="Ej: Curso de Marketing Digital"
                            class="w-full"
                            :class="{ 'p-invalid': !formData.nombre.trim() && errorMessage }"
                        />
                    </div>

                    <!-- Audiencia objetivo -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Audiencia Objetivo
                        </label>
                        <Textarea
                            v-model="formData.audiencia_objetivo"
                            placeholder="Ej: Emprendedores digitales de 25-40 años interesados en marketing..."
                            rows="3"
                            class="w-full"
                        />
                        <small class="text-gray-500">
                            Define quién es tu público objetivo (igual que el contexto de YouTube/Google Forms)
                        </small>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Descripción del Producto
                        </label>
                        <Textarea
                            v-model="formData.descripcion"
                            placeholder="Ej: Curso completo de marketing digital con estrategias probadas..."
                            rows="4"
                            class="w-full"
                        />
                        <small class="text-gray-500">
                            Describe tu producto o servicio en detalle
                        </small>
                    </div>

                    <!-- Mensaje de error -->
                    <Message v-if="errorMessage" severity="error" :closable="false">
                        {{ errorMessage }}
                    </Message>
                </div>

                <template #footer>
                    <Button
                        label="Cancelar"
                        icon="pi pi-times"
                        @click="closeDialog"
                        text
                    />
                    <Button
                        :label="isEditing ? 'Actualizar' : 'Crear'"
                        :icon="isEditing ? 'pi pi-check' : 'pi pi-plus'"
                        @click="saveProduct"
                        :disabled="!formData.nombre.trim()"
                    />
                </template>
            </Dialog>
        </div>
</template>

<style scoped>
/* Estilos adicionales si son necesarios */
</style>
