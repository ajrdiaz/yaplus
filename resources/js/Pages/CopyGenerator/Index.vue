<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dropdown from 'primevue/dropdown';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Textarea from 'primevue/textarea';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import ProgressBar from 'primevue/progressbar';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';

const props = defineProps({
    buyerPersonas: Array,
    copyTypes: Object,
    recentCopies: Array,
});

// Estado del formulario
const selectedPersona = ref(null);
const selectedCopyType = ref(null);
const customName = ref('');
const isGenerating = ref(false);
const generatedCopy = ref(null);
const errorMessage = ref('');

// Opciones para los dropdowns
const personaOptions = computed(() => {
    return props.buyerPersonas.map(persona => ({
        label: `${persona.nombre} (${persona.source} - ${persona.source_name})`,
        value: { id: persona.id, type: persona.type },
        ...persona
    }));
});

const copyTypeOptions = computed(() => {
    return Object.entries(props.copyTypes).map(([key, value]) => ({
        label: value,
        value: key
    }));
});

// Generar copy
const generateCopy = async () => {
    if (!selectedPersona.value || !selectedCopyType.value) {
        errorMessage.value = 'Por favor selecciona un Buyer Persona y un tipo de copy';
        return;
    }

    isGenerating.value = true;
    errorMessage.value = '';
    generatedCopy.value = null;

    try {
        const response = await axios.post(route('copy.generate'), {
            buyer_persona_id: selectedPersona.value.id,
            buyer_persona_type: selectedPersona.value.type,
            copy_type: selectedCopyType.value,
            custom_name: customName.value || null,
        });

        if (response.data.success) {
            generatedCopy.value = response.data.copy;
            
            // Recargar la página para actualizar los copies recientes
            setTimeout(() => {
                router.reload({ only: ['recentCopies'] });
            }, 1000);
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Error al generar el copy. Intenta nuevamente.';
        console.error('Error:', error);
    } finally {
        isGenerating.value = false;
    }
};

// Copiar al portapapeles
const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    // Aquí podrías agregar un toast notification
};

// Resetear formulario
const resetForm = () => {
    generatedCopy.value = null;
    customName.value = '';
    errorMessage.value = '';
};

// Eliminar copy
const deleteCopy = async (copyId) => {
    if (!confirm('¿Estás seguro de eliminar este copy?')) return;

    try {
        await axios.delete(route('copy.destroy', copyId));
        router.reload({ only: ['recentCopies'] });
    } catch (error) {
        console.error('Error al eliminar:', error);
    }
};

// Obtener el ícono según el tipo de copy
const getCopyIcon = (copyType) => {
    const icons = {
        'facebook_ad': 'pi pi-facebook',
        'google_ad': 'pi pi-google',
        'landing_hero': 'pi pi-desktop',
        'email_subject': 'pi pi-envelope',
        'email_body': 'pi pi-send',
        'instagram_post': 'pi pi-instagram',
        'linkedin_post': 'pi pi-linkedin',
        'twitter_thread': 'pi pi-twitter',
    };
    return icons[copyType] || 'pi pi-file';
};
</script>

<template>
    <AppLayout title="Generador de Copy con IA">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    🎯 Generador de Copy con IA
                </h1>
                <p class="text-gray-600">
                    Genera textos persuasivos para tus anuncios y landing pages basados en tus Buyer Personas
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Panel de Generación -->
                <div class="lg:col-span-2">
                    <Card>
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-sparkles text-purple-600"></i>
                                Nuevo Copy
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-4">
                                <!-- Selector de Buyer Persona -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Buyer Persona *
                                    </label>
                                    <Dropdown
                                        v-model="selectedPersona"
                                        :options="personaOptions"
                                        optionLabel="label"
                                        placeholder="Selecciona un Buyer Persona"
                                        class="w-full"
                                        :disabled="isGenerating"
                                        filter
                                    >
                                        <template #value="slotProps">
                                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                                <Tag :value="slotProps.value.source" severity="info" />
                                                <span>{{ slotProps.value.nombre }}</span>
                                            </div>
                                            <span v-else>{{ slotProps.placeholder }}</span>
                                        </template>
                                        <template #option="slotProps">
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-2">
                                                    <Tag :value="slotProps.option.source" :severity="slotProps.option.source === 'YouTube' ? 'danger' : 'success'" />
                                                    <span class="font-semibold">{{ slotProps.option.nombre }}</span>
                                                </div>
                                                <span class="text-sm text-gray-500">
                                                    {{ slotProps.option.source_name }} - {{ slotProps.option.edad }}
                                                </span>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- Selector de Tipo de Copy -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Tipo de Copy *
                                    </label>
                                    <Dropdown
                                        v-model="selectedCopyType"
                                        :options="copyTypeOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Selecciona el tipo de copy"
                                        class="w-full"
                                        :disabled="isGenerating"
                                    />
                                </div>

                                <!-- Nombre personalizado (opcional) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Nombre del Copy (opcional)
                                    </label>
                                    <InputText
                                        v-model="customName"
                                        placeholder="Ej: Campaña Black Friday 2025"
                                        class="w-full"
                                        :disabled="isGenerating"
                                    />
                                </div>

                                <!-- Mensaje de error -->
                                <Message v-if="errorMessage" severity="error" :closable="false">
                                    {{ errorMessage }}
                                </Message>

                                <!-- Botón de generar -->
                                <div class="flex gap-2">
                                    <Button
                                        label="Generar Copy con IA"
                                        icon="pi pi-sparkles"
                                        @click="generateCopy"
                                        :loading="isGenerating"
                                        :disabled="!selectedPersona || !selectedCopyType"
                                        class="flex-1"
                                        severity="success"
                                    />
                                    <Button
                                        v-if="generatedCopy"
                                        label="Nuevo"
                                        icon="pi pi-refresh"
                                        @click="resetForm"
                                        outlined
                                    />
                                </div>

                                <!-- Loading -->
                                <div v-if="isGenerating" class="text-center py-4">
                                    <ProgressBar mode="indeterminate" style="height: 6px" />
                                    <p class="text-sm text-gray-600 mt-2">
                                        ✨ Generando copy personalizado con IA...
                                    </p>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- Resultado Generado -->
                    <Card v-if="generatedCopy" class="mt-4">
                        <template #title>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i :class="getCopyIcon(generatedCopy.copy_type)" class="text-green-600"></i>
                                    {{ generatedCopy.copy_type_name }}
                                </div>
                                <Tag :value="`${generatedCopy.character_count} caracteres`" severity="info" />
                            </div>
                        </template>
                        <template #subtitle>
                            {{ generatedCopy.name }}
                        </template>
                        <template #content>
                            <div class="space-y-4">
                                <!-- Headline -->
                                <div v-if="generatedCopy.headline">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-sm font-semibold text-gray-700">TITULAR</label>
                                        <Button
                                            icon="pi pi-copy"
                                            text
                                            rounded
                                            @click="copyToClipboard(generatedCopy.headline)"
                                            v-tooltip.top="'Copiar'"
                                        />
                                    </div>
                                    <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                        <p class="font-semibold text-lg">{{ generatedCopy.headline }}</p>
                                    </div>
                                </div>

                                <!-- Subheadline -->
                                <div v-if="generatedCopy.subheadline">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-sm font-semibold text-gray-700">SUBTÍTULO</label>
                                        <Button
                                            icon="pi pi-copy"
                                            text
                                            rounded
                                            @click="copyToClipboard(generatedCopy.subheadline)"
                                            v-tooltip.top="'Copiar'"
                                        />
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <p>{{ generatedCopy.subheadline }}</p>
                                    </div>
                                </div>

                                <!-- Body -->
                                <div v-if="generatedCopy.body">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-sm font-semibold text-gray-700">TEXTO</label>
                                        <Button
                                            icon="pi pi-copy"
                                            text
                                            rounded
                                            @click="copyToClipboard(generatedCopy.body)"
                                            v-tooltip.top="'Copiar'"
                                        />
                                    </div>
                                    <div class="p-3 bg-white rounded-lg border border-gray-300">
                                        <p class="whitespace-pre-wrap">{{ generatedCopy.body }}</p>
                                    </div>
                                </div>

                                <!-- CTA -->
                                <div v-if="generatedCopy.cta">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-sm font-semibold text-gray-700">CALL TO ACTION</label>
                                        <Button
                                            icon="pi pi-copy"
                                            text
                                            rounded
                                            @click="copyToClipboard(generatedCopy.cta)"
                                            v-tooltip.top="'Copiar'"
                                        />
                                    </div>
                                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                                        <p class="font-semibold text-green-700">{{ generatedCopy.cta }}</p>
                                    </div>
                                </div>

                                <!-- Additional Data (Google Ads, Landing Hero, etc) -->
                                <div v-if="generatedCopy.additional_data">
                                    <!-- Google Ads Headlines -->
                                    <div v-if="generatedCopy.additional_data.headlines" class="space-y-2">
                                        <label class="text-sm font-semibold text-gray-700">HEADLINES ADICIONALES</label>
                                        <div v-for="(headline, idx) in generatedCopy.additional_data.headlines" :key="idx" class="p-2 bg-purple-50 rounded border border-purple-200 text-sm">
                                            {{ headline }}
                                        </div>
                                    </div>

                                    <!-- Landing Benefits -->
                                    <div v-if="generatedCopy.additional_data.benefits" class="space-y-2">
                                        <label class="text-sm font-semibold text-gray-700">BENEFICIOS</label>
                                        <ul class="space-y-1">
                                            <li v-for="(benefit, idx) in generatedCopy.additional_data.benefits" :key="idx" class="flex items-start gap-2">
                                                <i class="pi pi-check-circle text-green-600 mt-1"></i>
                                                <span>{{ benefit }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Email Subjects -->
                                    <div v-if="generatedCopy.additional_data.all_subjects" class="space-y-2">
                                        <label class="text-sm font-semibold text-gray-700">VARIACIONES DE ASUNTO</label>
                                        <div v-for="(subject, idx) in generatedCopy.additional_data.all_subjects" :key="idx" class="p-2 bg-yellow-50 rounded border border-yellow-200 text-sm flex items-center justify-between">
                                            <span>{{ subject }}</span>
                                            <Button icon="pi pi-copy" text rounded size="small" @click="copyToClipboard(subject)" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Copiar todo -->
                                <div class="pt-4 border-t">
                                    <Button
                                        label="Copiar Todo el Copy"
                                        icon="pi pi-copy"
                                        @click="copyToClipboard(JSON.stringify(generatedCopy, null, 2))"
                                        outlined
                                        class="w-full"
                                    />
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Panel Lateral: Copies Recientes -->
                <div class="lg:col-span-1">
                    <Card>
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-history text-gray-600"></i>
                                Copies Recientes
                            </div>
                        </template>
                        <template #content>
                            <div v-if="recentCopies.length === 0" class="text-center py-8 text-gray-500">
                                <i class="pi pi-inbox text-4xl mb-2"></i>
                                <p>No hay copies generados aún</p>
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="copy in recentCopies"
                                    :key="copy.id"
                                    class="p-3 border rounded-lg hover:bg-gray-50 transition-colors cursor-pointer"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <i :class="getCopyIcon(copy.copy_type)" class="text-sm"></i>
                                                <span class="text-xs font-medium text-gray-500">{{ copy.copy_type_name }}</span>
                                            </div>
                                            <p class="text-sm font-semibold text-gray-800 truncate" v-tooltip.top="copy.headline">
                                                {{ copy.headline || copy.name }}
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">{{ copy.created_at }}</p>
                                        </div>
                                        <Button
                                            icon="pi pi-trash"
                                            text
                                            rounded
                                            size="small"
                                            severity="danger"
                                            @click.stop="deleteCopy(copy.id)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- Guía rápida -->
                    <Card class="mt-4">
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-info-circle text-blue-600"></i>
                                Guía Rápida
                            </div>
                        </template>
                        <template #content>
                            <div class="text-sm space-y-2 text-gray-600">
                                <p><strong>1.</strong> Selecciona un Buyer Persona (de YouTube o Google Forms)</p>
                                <p><strong>2.</strong> Elige el tipo de copy que necesitas</p>
                                <p><strong>3.</strong> La IA generará un texto personalizado basado en los datos del buyer</p>
                                <p><strong>4.</strong> Copia y usa el texto en tus campañas</p>
                                <p class="pt-2 border-t mt-3 text-xs">
                                    💡 <strong>Tip:</strong> Puedes regenerar el copy cuantas veces quieras para obtener diferentes variaciones
                                </p>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Estilos personalizados si son necesarios */
</style>
