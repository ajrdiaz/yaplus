<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
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
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';

const props = defineProps({
    copyTypes: Object,
    recentCopies: Array,
    products: Array,
});

// Estado del formulario
const selectedProduct = ref(null);
const selectedCopyType = ref(null);
const customName = ref('');
const isGenerating = ref(false);
const generatedCopy = ref(null);
const errorMessage = ref('');

// Campos específicos para Facebook Ads
const facebookAdObjective = ref('');
const facebookAdTone = ref('');
const facebookAdAngle = ref('');

// Buyer persona seleccionado de los top 5 (null = usar todos los datos consolidados)
const selectedBuyerPersona = ref(null);

// Número de variaciones a generar (1-3)
const variationsCount = ref(1);

// Opciones para los dropdowns
const copyTypeOptions = computed(() => {
    return Object.entries(props.copyTypes).map(([key, value]) => ({
        label: value,
        value: key
    }));
});

// Producto seleccionado con sus datos
const selectedProductData = computed(() => {
    if (!selectedProduct.value) return null;
    return props.products.find(p => p.id === selectedProduct.value);
});

// Top 5 buyer personas del producto seleccionado para el dropdown
const buyerPersonaOptions = computed(() => {
    if (!selectedProductData.value?.has_consolidated_data) return [];

    const top5 = selectedProductData.value.top_5_buyer_personas || [];

    // Agregar opción "Todos" al principio
    const options = [
        { label: 'Todos los Buyer Personas consolidados', value: null }
    ];

    // Agregar cada buyer persona del top 5
    top5.forEach((persona, index) => {
        options.push({
            label: `${persona.nombre} (${persona.source_name})`,
            value: index,
            data: persona
        });
    });

    return options;
});

// Opciones para objetivos de Facebook Ads
const facebookObjectiveOptions = [
    { label: 'Generar tráfico al sitio web', value: 'traffic' },
    { label: 'Generar conversiones/ventas', value: 'conversions' },
    { label: 'Generar leads/registros', value: 'leads' },
    { label: 'Aumentar reconocimiento de marca', value: 'awareness' },
    { label: 'Generar interacción (engagement)', value: 'engagement' },
];

// Opciones para tono de comunicación
const toneOptions = [
    { label: 'Profesional', value: 'professional' },
    { label: 'Casual y amigable', value: 'casual' },
    { label: 'Urgente y directo', value: 'urgent' },
    { label: 'Inspiracional', value: 'inspirational' },
    { label: 'Educativo', value: 'educational' },
    { label: 'Emocional', value: 'emotional' },
];

// Opciones para número de variaciones
const variationsOptions = [
    { label: '1 variación', value: 1 },
    { label: '2 variaciones', value: 2 },
    { label: '3 variaciones', value: 3 },
];

// Watcher para establecer valores por defecto cuando cambie el tipo de copy
watch(selectedCopyType, (newValue) => {
    if (newValue === 'facebook_ad') {
        // Establecer valores por defecto para Facebook Ads
        if (!facebookAdObjective.value) {
            facebookAdObjective.value = 'conversions';
        }
        if (!facebookAdTone.value) {
            facebookAdTone.value = 'emotional';
        }
    } else {
        // Limpiar campos si no es Facebook Ad
        facebookAdObjective.value = '';
        facebookAdTone.value = '';
        facebookAdAngle.value = '';
    }
});

// Watcher para resetear el buyer persona cuando cambie el producto
watch(selectedProduct, () => {
    selectedBuyerPersona.value = null;
});

// Generar copy
const generateCopy = async () => {
    if (!selectedProduct.value || !selectedCopyType.value) {
        errorMessage.value = 'Por favor selecciona un Producto y un tipo de copy';
        return;
    }

    // Verificar que el producto tenga datos consolidados
    if (!selectedProductData.value?.has_consolidated_data) {
        errorMessage.value = 'Este producto no tiene datos consolidados. Por favor, ve a la sección de Productos y consolida los datos primero.';
        return;
    }

    // Validar campos específicos de Facebook Ads
    if (selectedCopyType.value === 'facebook_ad') {
        if (!facebookAdObjective.value || !facebookAdTone.value || !facebookAdAngle.value) {
            errorMessage.value = 'Por favor completa todos los campos requeridos para el anuncio de Facebook';
            return;
        }
    }

    isGenerating.value = true;
    errorMessage.value = '';
    generatedCopy.value = null;

    try {
        const payload = {
            product_id: selectedProduct.value,
            copy_type: selectedCopyType.value,
            custom_name: customName.value || null,
            selected_buyer_persona_index: selectedBuyerPersona.value,
            variations_count: variationsCount.value,
        };

        // Agregar campos específicos de Facebook si aplica
        if (selectedCopyType.value === 'facebook_ad') {
            payload.facebook_ad_objective = facebookAdObjective.value;
            payload.facebook_ad_tone = facebookAdTone.value;
            payload.facebook_ad_angle = facebookAdAngle.value;
        }

        const response = await axios.post(route('copy.generate'), payload);

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
    // Limpiar el copy generado
    generatedCopy.value = null;

    // Resetear campos del formulario
    selectedProduct.value = null;
    selectedCopyType.value = null;
    customName.value = '';
    errorMessage.value = '';

    // Resetear campos específicos de Facebook Ads
    facebookAdObjective.value = '';
    facebookAdTone.value = '';
    facebookAdAngle.value = '';

    // Resetear selección de buyer persona y variaciones
    selectedBuyerPersona.value = null;
    variationsCount.value = 1;
};

// Ver detalles de un copy
const viewCopy = async (copyId) => {
    try {
        const response = await axios.get(route('copy.show', copyId));
        if (response.data.copy) {
            generatedCopy.value = response.data.copy;
            // Scroll hacia el resultado
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    } catch (error) {
        console.error('Error al cargar el copy:', error);
    }
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

// Formatear texto con saltos de línea
const formatText = (text) => {
    if (!text) return '';
    return text.replace(/\n/g, '<br>');
};
</script>

<template>
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

            <!-- Panel de Generación -->
            <div>
                    <Card>
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-sparkles text-purple-600"></i>
                                Nuevo Copy
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-4">
                                <!-- Selector de Producto -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Producto *
                                    </label>
                                    <Dropdown
                                        v-model="selectedProduct"
                                        :options="products"
                                        optionLabel="nombre"
                                        optionValue="id"
                                        placeholder="Selecciona un producto"
                                        class="w-full"
                                        :disabled="isGenerating"
                                        filter
                                    >
                                        <template #value="slotProps">
                                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                                <i class="pi pi-box text-primary"></i>
                                                <span>{{ products.find(p => p.id === slotProps.value)?.nombre }}</span>
                                            </div>
                                            <span v-else>{{ slotProps.placeholder }}</span>
                                        </template>
                                        <template #option="slotProps">
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-semibold">{{ slotProps.option.nombre }}</span>
                                                    <Tag
                                                        v-if="slotProps.option.has_consolidated_data"
                                                        value="Consolidado"
                                                        severity="success"
                                                        size="small"
                                                    />
                                                    <Tag
                                                        v-else
                                                        value="Sin consolidar"
                                                        severity="warning"
                                                        size="small"
                                                    />
                                                </div>
                                                <span v-if="slotProps.option.has_consolidated_data" class="text-xs text-gray-500 mt-1">
                                                    {{ slotProps.option.total_buyer_personas }} buyer personas consolidados
                                                    <span v-if="slotProps.option.ultima_consolidacion">
                                                        · {{ slotProps.option.ultima_consolidacion }}
                                                    </span>
                                                </span>
                                                <span v-else class="text-xs text-orange-600 mt-1">
                                                    <i class="pi pi-exclamation-triangle"></i>
                                                    Requiere consolidación de datos
                                                </span>
                                            </div>
                                        </template>
                                    </Dropdown>

                                    <!-- Advertencia si el producto no tiene datos consolidados -->
                                    <div v-if="selectedProductData && !selectedProductData.has_consolidated_data" class="mt-2">
                                        <Message severity="warn" :closable="false">
                                            <div class="flex flex-col gap-2">
                                                <p class="font-medium">Este producto no tiene datos consolidados</p>
                                                <p class="text-sm">Ve a la sección de Productos y haz clic en "Consolidar Datos" para procesar los buyer personas asociados.</p>
                                            </div>
                                        </Message>
                                    </div>

                                    <!-- Info del producto si tiene datos consolidados -->
                                    <div v-if="selectedProductData && selectedProductData.has_consolidated_data" class="mt-2 p-3 bg-green-50 rounded-lg border border-green-200">
                                        <div class="flex items-center gap-2 text-sm text-green-800">
                                            <i class="pi pi-check-circle"></i>
                                            <span class="font-medium">Producto listo para generar copy</span>
                                        </div>
                                        <p class="text-xs text-green-700 mt-1">
                                            {{ selectedProductData.total_buyer_personas }} buyer personas consolidados
                                            <span v-if="selectedProductData.ultima_consolidacion">
                                                · Última consolidación: {{ selectedProductData.ultima_consolidacion }}
                                            </span>
                                        </p>
                                    </div>
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

                                <!-- Selector de Buyer Persona -->
                                <div v-if="selectedProductData?.has_consolidated_data && buyerPersonaOptions.length > 1">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Buyer Persona
                                    </label>
                                    <Dropdown
                                        v-model="selectedBuyerPersona"
                                        :options="buyerPersonaOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Selecciona un buyer persona"
                                        class="w-full"
                                        :disabled="isGenerating"
                                    >
                                        <template #value="slotProps">
                                            <div v-if="slotProps.value === null">
                                                <i class="pi pi-users text-primary mr-2"></i>
                                                <span>Todos los Buyer Personas consolidados</span>
                                            </div>
                                            <div v-else class="flex items-center gap-2">
                                                <i class="pi pi-user text-primary"></i>
                                                <span>{{ buyerPersonaOptions.find(o => o.value === slotProps.value)?.label }}</span>
                                            </div>
                                        </template>
                                        <template #option="slotProps">
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-2">
                                                    <i :class="slotProps.option.value === null ? 'pi pi-users' : 'pi pi-user'" class="text-primary"></i>
                                                    <span :class="{ 'font-semibold': slotProps.option.value === null }">
                                                        {{ slotProps.option.label }}
                                                    </span>
                                                </div>
                                                <span v-if="slotProps.option.value === null" class="text-xs text-gray-500 mt-1 ml-6">
                                                    Usar datos consolidados de todos ({{ selectedProductData.total_buyer_personas }} buyer personas)
                                                </span>
                                                <div v-else class="text-xs text-gray-500 mt-1 ml-6">
                                                    <div v-if="slotProps.option.data">
                                                        {{ slotProps.option.data.edad }} · {{ slotProps.option.data.ocupacion }}
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </Dropdown>
                                    <small class="text-gray-500 text-xs mt-1 block">
                                        Selecciona uno de los 5 mejores Buyer Personas o usa todos los datos consolidados
                                    </small>
                                </div>

                                <!-- Selector de Número de Variaciones -->
                                <div v-if="selectedCopyType === 'facebook_ad' || selectedCopyType === 'landing_hero'">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Número de Variaciones
                                    </label>
                                    <Dropdown
                                        v-model="variationsCount"
                                        :options="variationsOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Selecciona cuántas variaciones generar"
                                        class="w-full"
                                        :disabled="isGenerating"
                                    >
                                        <template #value="slotProps">
                                            <div class="flex items-center gap-2">
                                                <i class="pi pi-clone text-primary"></i>
                                                <span>{{ variationsOptions.find(o => o.value === slotProps.value)?.label }}</span>
                                            </div>
                                        </template>
                                    </Dropdown>
                                    <small class="text-gray-500 text-xs mt-1 block">
                                        La IA generará múltiples variaciones del mismo copy para que puedas A/B testear
                                    </small>
                                </div>

                                <!-- Campos específicos para Facebook Ads -->
                                <div v-if="selectedCopyType === 'facebook_ad'" class="space-y-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="pi pi-facebook text-blue-600"></i>
                                        <span class="text-sm font-semibold text-blue-900">Configuración del Anuncio de Facebook/Instagram</span>
                                    </div>

                                    <!-- Objetivo específico -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Objetivo específico del anuncio *
                                        </label>
                                        <Dropdown
                                            v-model="facebookAdObjective"
                                            :options="facebookObjectiveOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="¿Qué quieres lograr con este anuncio?"
                                            class="w-full"
                                            :disabled="isGenerating"
                                        />
                                    </div>

                                    <!-- Tono de comunicación -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Tono de la comunicación *
                                        </label>
                                        <Dropdown
                                            v-model="facebookAdTone"
                                            :options="toneOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="¿Cómo quieres comunicarte con tu audiencia?"
                                            class="w-full"
                                            :disabled="isGenerating"
                                        />
                                    </div>

                                    <!-- Ángulo de venta -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Ángulo de venta principal a trabajar *
                                        </label>
                                        <Textarea
                                            v-model="facebookAdAngle"
                                            placeholder="Ej: Ahorro de tiempo, Resultados garantizados, Precio especial por tiempo limitado, etc."
                                            rows="3"
                                            class="w-full"
                                            :disabled="isGenerating"
                                        />
                                        <small class="text-gray-500 text-xs">
                                            Describe el beneficio o ventaja principal que quieres destacar en el anuncio
                                        </small>
                                    </div>
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
                                <div class="flex gap-2 mt-3">
                                    <Button
                                        label="Generar Copy con IA"
                                        icon="pi pi-sparkles"
                                        @click="generateCopy"
                                        :loading="isGenerating"
                                        :disabled="!selectedProduct || !selectedCopyType || !selectedProductData?.has_consolidated_data"
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
                                <!-- Formato específico para Facebook Ads -->
                                <div v-if="generatedCopy.copy_type === 'facebook_ad' && generatedCopy.additional_data">
                                    <!-- Si hay variaciones múltiples, mostrar en tabs -->
                                    <div v-if="generatedCopy.additional_data.variations && generatedCopy.additional_data.variations.length > 1">
                                        <div class="mb-3 flex items-center gap-2 p-3 bg-purple-50 rounded-lg border border-purple-200">
                                            <i class="pi pi-clone text-purple-600"></i>
                                            <span class="font-semibold text-purple-900">{{ generatedCopy.additional_data.variations.length }} Variaciones Generadas para A/B Testing</span>
                                        </div>

                                        <TabView>
                                            <TabPanel v-for="(variation, index) in generatedCopy.additional_data.variations" :key="index" :header="`Variación ${index + 1}`">
                                                <div class="space-y-4">
                                                    <!-- Texto Principal Corto -->
                                                    <div v-if="variation.texto_corto">
                                                        <div class="flex items-center justify-between">
                                                            <label class="text-sm font-semibold text-gray-700">
                                                                📝 TEXTO PRINCIPAL (VERSIÓN CORTA)
                                                                <span class="text-xs text-gray-500 ml-2">({{ variation.texto_corto.length }}/125 caracteres)</span>
                                                            </label>
                                                            <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(variation.texto_corto)" v-tooltip.top="'Copiar'" />
                                                        </div>
                                                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                                            <p class="font-medium">{{ variation.texto_corto }}</p>
                                                        </div>
                                                    </div>

                                                    <!-- Texto Principal Largo -->
                                                    <div v-if="variation.texto_largo">
                                                        <div class="flex items-center justify-between mt-4">
                                                            <label class="text-sm font-semibold text-gray-700">
                                                                📄 TEXTO PRINCIPAL (VERSIÓN LARGA)
                                                                <span class="text-xs text-gray-500 ml-2">({{ variation.texto_largo.length }}/400-700 caracteres)</span>
                                                            </label>
                                                            <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(variation.texto_largo)" v-tooltip.top="'Copiar'" />
                                                        </div>
                                                        <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                                                            <p v-html="formatText(variation.texto_largo)"></p>
                                                        </div>
                                                    </div>

                                                    <!-- Titular Corto -->
                                                    <div v-if="variation.titular_corto">
                                                        <div class="flex items-center justify-between mt-4">
                                                            <label class="text-sm font-semibold text-gray-700">
                                                                🎯 TITULAR (VERSIÓN CORTA)
                                                                <span class="text-xs text-gray-500 ml-2">({{ variation.titular_corto.length }}/27 caracteres)</span>
                                                            </label>
                                                            <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(variation.titular_corto)" v-tooltip.top="'Copiar'" />
                                                        </div>
                                                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                                            <p class="font-bold text-lg">{{ variation.titular_corto }}</p>
                                                        </div>
                                                    </div>

                                                    <!-- Titular Largo -->
                                                    <div v-if="variation.titular_largo">
                                                        <div class="flex items-center justify-between mt-4">
                                                            <label class="text-sm font-semibold text-gray-700">
                                                                🎯 TITULAR (VERSIÓN LARGA)
                                                                <span class="text-xs text-gray-500 ml-2">({{ variation.titular_largo.length }}/60 caracteres)</span>
                                                            </label>
                                                            <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(variation.titular_largo)" v-tooltip.top="'Copiar'" />
                                                        </div>
                                                        <div class="p-3 bg-orange-50 rounded-lg border border-orange-200">
                                                            <p class="font-semibold text-base">{{ variation.titular_largo }}</p>
                                                        </div>
                                                    </div>

                                                    <!-- Descripción del Titular -->
                                                    <div v-if="variation.descripcion">
                                                        <div class="flex items-center justify-between mt-4">
                                                            <label class="text-sm font-semibold text-gray-700">
                                                                💬 DESCRIPCIÓN DEL TITULAR
                                                                <span class="text-xs text-gray-500 ml-2">({{ variation.descripcion.length }}/60 caracteres)</span>
                                                            </label>
                                                            <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(variation.descripcion)" v-tooltip.top="'Copiar'" />
                                                        </div>
                                                        <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                                                            <p class="font-medium text-green-800">{{ variation.descripcion }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </TabPanel>
                                        </TabView>
                                    </div>

                                    <!-- Variación única (formato anterior) -->
                                    <div v-else>
                                        <!-- Texto Principal Corto -->
                                        <div v-if="generatedCopy.additional_data.texto_corto">
                                            <div class="flex items-center justify-between">
                                                <label class="text-sm font-semibold text-gray-700">
                                                    📝 TEXTO PRINCIPAL (VERSIÓN CORTA)
                                                    <span class="text-xs text-gray-500 ml-2">({{ generatedCopy.additional_data.texto_corto.length }}/125 caracteres)</span>
                                                </label>
                                                <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(generatedCopy.additional_data.texto_corto)" v-tooltip.top="'Copiar'" />
                                            </div>
                                            <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                                <p class="font-medium">{{ generatedCopy.additional_data.texto_corto }}</p>
                                            </div>
                                        </div>

                                        <!-- Texto Principal Largo -->
                                        <div v-if="generatedCopy.additional_data.texto_largo">
                                            <div class="flex items-center justify-between mt-4">
                                                <label class="text-sm font-semibold text-gray-700">
                                                    📄 TEXTO PRINCIPAL (VERSIÓN LARGA)
                                                    <span class="text-xs text-gray-500 ml-2">({{ generatedCopy.additional_data.texto_largo.length }}/400-700 caracteres)</span>
                                                </label>
                                                <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(generatedCopy.additional_data.texto_largo)" v-tooltip.top="'Copiar'" />
                                            </div>
                                            <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                                                <p v-html="formatText(generatedCopy.additional_data.texto_largo)"></p>
                                            </div>
                                        </div>

                                        <!-- Titular Corto -->
                                        <div v-if="generatedCopy.additional_data.titular_corto">
                                            <div class="flex items-center justify-between mt-4">
                                                <label class="text-sm font-semibold text-gray-700">
                                                    🎯 TITULAR (VERSIÓN CORTA)
                                                    <span class="text-xs text-gray-500 ml-2">({{ generatedCopy.additional_data.titular_corto.length }}/27 caracteres)</span>
                                                </label>
                                                <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(generatedCopy.additional_data.titular_corto)" v-tooltip.top="'Copiar'" />
                                            </div>
                                            <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                                <p class="font-bold text-lg">{{ generatedCopy.additional_data.titular_corto }}</p>
                                            </div>
                                        </div>

                                        <!-- Titular Largo -->
                                        <div v-if="generatedCopy.additional_data.titular_largo">
                                            <div class="flex items-center justify-between mt-4">
                                                <label class="text-sm font-semibold text-gray-700">
                                                    🎯 TITULAR (VERSIÓN LARGA)
                                                    <span class="text-xs text-gray-500 ml-2">({{ generatedCopy.additional_data.titular_largo.length }}/60 caracteres)</span>
                                                </label>
                                                <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(generatedCopy.additional_data.titular_largo)" v-tooltip.top="'Copiar'" />
                                            </div>
                                            <div class="p-3 bg-orange-50 rounded-lg border border-orange-200">
                                                <p class="font-semibold text-base">{{ generatedCopy.additional_data.titular_largo }}</p>
                                            </div>
                                        </div>

                                        <!-- Descripción del Titular -->
                                        <div v-if="generatedCopy.additional_data.descripcion">
                                            <div class="flex items-center justify-between mt-4">
                                                <label class="text-sm font-semibold text-gray-700">
                                                    💬 DESCRIPCIÓN DEL TITULAR
                                                    <span class="text-xs text-gray-500 ml-2">({{ generatedCopy.additional_data.descripcion.length }}/60 caracteres)</span>
                                                </label>
                                                <Button class="h-auto py-0" icon="pi pi-copy" text rounded @click="copyToClipboard(generatedCopy.additional_data.descripcion)" v-tooltip.top="'Copiar'" />
                                            </div>
                                            <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                                                <p class="font-medium text-green-800">{{ generatedCopy.additional_data.descripcion }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Formato específico para Landing Hero -->
                                <div v-else-if="generatedCopy.copy_type === 'landing_hero' && generatedCopy.additional_data">
                                    <!-- Si hay variaciones múltiples, mostrar en tabs -->
                                    <div v-if="generatedCopy.additional_data.variations && generatedCopy.additional_data.variations.length > 1">
                                        <div class="mb-3 flex items-center gap-2 p-3 bg-purple-50 rounded-lg border border-purple-200">
                                            <i class="pi pi-clone text-purple-600"></i>
                                            <span class="font-semibold text-purple-900">{{ generatedCopy.additional_data.variations.length }} Variaciones Generadas para A/B Testing</span>
                                        </div>

                                        <TabView>
                                            <TabPanel v-for="(variation, index) in generatedCopy.additional_data.variations" :key="index" :header="`Variación ${index + 1}`">
                                                <div class="space-y-4">
                                                    <!-- H1 -->
                                                    <div v-if="variation.h1">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <label class="text-sm font-semibold text-gray-700">🎯 TITULAR PRINCIPAL (H1)</label>
                                                            <Button icon="pi pi-copy" text rounded @click="copyToClipboard(variation.h1)" v-tooltip.top="'Copiar'" />
                                                        </div>
                                                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                                            <p class="font-bold text-2xl">{{ variation.h1 }}</p>
                                                        </div>
                                                    </div>

                                                    <!-- H2 -->
                                                    <div v-if="variation.h2">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <label class="text-sm font-semibold text-gray-700">💬 SUBTÍTULO (H2)</label>
                                                            <Button icon="pi pi-copy" text rounded @click="copyToClipboard(variation.h2)" v-tooltip.top="'Copiar'" />
                                                        </div>
                                                        <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                                                            <p class="font-semibold text-lg">{{ variation.h2 }}</p>
                                                        </div>
                                                    </div>

                                                    <!-- Benefits -->
                                                    <div v-if="variation.benefits && variation.benefits.length > 0">
                                                        <label class="text-sm font-semibold text-gray-700 mb-2 block">✅ BENEFICIOS CLAVE</label>
                                                        <ul class="space-y-2">
                                                            <li v-for="(benefit, idx) in variation.benefits" :key="idx" class="flex items-start gap-2 p-2 bg-green-50 rounded-lg border border-green-200">
                                                                <i class="pi pi-check-circle text-green-600 mt-1"></i>
                                                                <span>{{ benefit }}</span>
                                                            </li>
                                                        </ul>
                                                    </div>

                                                    <!-- CTAs -->
                                                    <div class="flex gap-2">
                                                        <div v-if="variation.cta_primary" class="flex-1">
                                                            <div class="flex items-center justify-between mb-2">
                                                                <label class="text-sm font-semibold text-gray-700">🔘 CTA PRINCIPAL</label>
                                                                <Button icon="pi pi-copy" text rounded @click="copyToClipboard(variation.cta_primary)" v-tooltip.top="'Copiar'" />
                                                            </div>
                                                            <div class="p-3 bg-green-100 rounded-lg border border-green-300">
                                                                <p class="font-bold text-green-800">{{ variation.cta_primary }}</p>
                                                            </div>
                                                        </div>
                                                        <div v-if="variation.cta_secondary" class="flex-1">
                                                            <div class="flex items-center justify-between mb-2">
                                                                <label class="text-sm font-semibold text-gray-700">🔘 CTA SECUNDARIO</label>
                                                                <Button icon="pi pi-copy" text rounded @click="copyToClipboard(variation.cta_secondary)" v-tooltip.top="'Copiar'" />
                                                            </div>
                                                            <div class="p-3 bg-gray-100 rounded-lg border border-gray-300">
                                                                <p class="font-semibold text-gray-700">{{ variation.cta_secondary }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </TabPanel>
                                        </TabView>
                                    </div>

                                    <!-- Variación única para Landing Hero (formato anterior) -->
                                    <div v-else>
                                        <!-- Use generic format below -->
                                    </div>
                                </div>

                                <!-- Formato genérico para otros tipos de copy -->
                                <div v-else>
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

            <!-- Historial de Copies - DataTable completo abajo -->
            <div class="mt-6">
                <Card>
                    <template #title>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-history text-gray-600"></i>
                            Historial de Copies
                        </div>
                    </template>
                    <template #content>
                        <DataTable
                            :value="recentCopies"
                            paginator
                            :rows="10"
                            :rowsPerPageOptions="[5, 10, 20, 50]"
                            tableStyle="min-width: 50rem"
                            :emptyMessage="'No hay copies generados aún'"
                            sortField="created_at"
                            :sortOrder="-1"
                            stripedRows
                        >
                            <Column field="copy_type" header="Tipo" sortable style="width: 15%">
                                <template #body="slotProps">
                                    <div class="flex items-center gap-2">
                                        <i :class="getCopyIcon(slotProps.data.copy_type)"></i>
                                        <span class="text-sm">{{ slotProps.data.copy_type_name }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="name" header="Nombre" sortable style="width: 20%">
                                <template #body="slotProps">
                                    <span class="font-medium">{{ slotProps.data.name }}</span>
                                </template>
                            </Column>

                            <Column field="headline" header="Titular" sortable style="width: 35%">
                                <template #body="slotProps">
                                    <p class="text-sm text-gray-700 truncate max-w-md" v-tooltip.top="slotProps.data.headline">
                                        {{ slotProps.data.headline }}
                                    </p>
                                </template>
                            </Column>

                            <Column field="character_count" header="Caracteres" sortable style="width: 10%; text-align: center">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.character_count" severity="info" />
                                </template>
                            </Column>

                            <Column field="created_at" header="Fecha" sortable style="width: 12%">
                                <template #body="slotProps">
                                    <span class="text-sm text-gray-600">{{ slotProps.data.created_at }}</span>
                                </template>
                            </Column>

                            <Column header="Acciones" style="width: 8%; text-align: center">
                                <template #body="slotProps">
                                    <div class="flex items-center justify-center gap-1">
                                        <Button
                                            icon="pi pi-eye"
                                            text
                                            rounded
                                            size="small"
                                            severity="info"
                                            @click="viewCopy(slotProps.data.id)"
                                            v-tooltip.top="'Ver detalles'"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            text
                                            rounded
                                            size="small"
                                            severity="danger"
                                            @click="deleteCopy(slotProps.data.id)"
                                            v-tooltip.top="'Eliminar'"
                                        />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </template>
                </Card>
            </div>
    </div>
</template>

<style scoped>
/* Estilos personalizados si son necesarios */
</style>
