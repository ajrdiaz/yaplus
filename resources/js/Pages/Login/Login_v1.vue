<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useLayout } from '@/Layouts/composables/layout';

import Button from "primevue/button";
import Carousel from 'primevue/carousel';
import Checkbox from "primevue/checkbox";
import Dropdown from "primevue/dropdown";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Password from "primevue/password";

defineProps({
    canResetPassword: Boolean,
    status: String,
});

// const rememberMe = ref(false);
const { layoutConfig } = useLayout();
const invalid = ref(false);

const darkMode = computed(() => {
    return layoutConfig.colorScheme.value !== 'light';
});

const navigateToRegister = () => {
    // router.visit("/register"); // router.push({ name: '/login' });
};

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
const sel_empresa = ref();
const empresas = ref([
    { bd_name: 'db0003_02', label: 'Integrator SAC' },
    { bd_name: 'db0003_01', label: 'Acuario Service SAC' },
])
</script>
<script>
import AppLogo from '@/Components/Images/Logo.vue';
import NoLayout from '@/Layouts/NoLayout.vue';

export default {
    components: {
        AppLogo
    },
    layout: NoLayout,
    data() {
        return {
            fillColor: 'var(--primary-color)',
            svgInlineStyle: 'max-width: 165px;',
            slides: [
                { name: 'Descubre nuestra diferentes versiones, adaptadas a diversos rubros comerciales', hasta: '2024-11-30', image: 'https://integrator.pe/assets/img/versiones/comercial.jpg' },
                { name: 'Mantente conectado desde cualquier dispositivo con internet', hasta: '2024-11-30', image: 'https://venturebeat.com/wp-content/uploads/2022/05/GettyImages-1307770579-e1652718837966.jpg?fit=1024&strip=all' },
                { name: 'Manten integradas todas las áreas de tu negocio', hasta: '2024-11-30', image: 'https://images.pexels.com/photos/4348078/pexels-photo-4348078.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1' },
                { name: 'Ahorra tiempo y recursos con nuestra solución en la nube', hasta: '2024-11-30', image: 'https://images.pexels.com/photos/926390/pexels-photo-926390.jpeg?auto=compress&cs=tinysrgb&w=800' }
            ]
        };
    },
    computed: {
        filteredSlides() {
            const currentDate = new Date();
            return this.slides.filter(slide => new Date(slide.hasta) >= currentDate);
        }
    }
}
</script>

<style type="text/css">
.app-logo-small{
    display: none !important
}
.p-carousel-items-container {
    height: 100% !important;
}

.gradient-before {
    background-color: #818181;
    box-shadow: inset 0px -80px 40px -80px #818181;
}
</style>
<template>

    <Head title="" />
    <form name="login" class="px-1 min-h-screen flex justify-content-center align-items-center mx-auto"
        style="max-width:780px" @submit.prevent="submit">
        <div class="flex w-full h-full border-round overflow-hidden">
            <div v-if="filteredSlides.length > 0" class="md:w-7 hidden md:block">
                <Carousel class="h-full" :value="filteredSlides" :numVisible="1" :numScroll="1" :showIndicators="false"
                    :containerClass="'h-full relative'" :contentClass="'h-full'"
                    :prevButtonProps="{ class: 'absolute', style: { zIndex: '1' } }"
                    :nextButtonProps="{ class: 'absolute right-0' }" circular :autoplayInterval="8000">
                    <template #item="slotProps">
                        <div :style="{ 'background-image': 'url(' /*+ 'https://primefaces.org/cdn/primevue/images/product/'*/ + slotProps.data.image + ')' }"
                            :alt="slotProps.data.name"
                            class="w-full h-full bg-cover bg-no-repeat bg-center gradient-before">
                            <span class="absolute block w-full text-white bottom-0 p-4 text-2xl font-semibold"
                                style="text-shadow: #000 1px 0 10px;">{{
            slotProps.data.name }}</span>
                        </div>
                    </template>
                </Carousel>
            </div>
            <div class="w-full md:w-5 m-auto">
                <div class="md:border-1 surface-border border-round md:surface-card py-7 px-4 sm:px-6 md:px-7 z-1"
                    style="min-width:300px">
                    <div class="mb-5 text-center">
                        <AppLogo :fill="fillColor" :svgStyle="svgInlineStyle" />
                    </div>
                    <div class="mb-3">
                        <div class="text-900 text-2xl font-bold mb-2">Hola, bienvenido</div>
                        <!-- <span class="text-600 font-medium">Please enter your details</span> -->
                    </div>
                    <div class="flex flex-column">
                        <!-- <IconField iconPosition="left" class="w-full mb-4">
                            <InputIcon class="pi pi-building" style="z-index:1" />
                            <Dropdown v-model="sel_empresa" :options="empresas" filter checkmark
                                :highlightOnSelect="false" :emptyFilterMessage="'Sin resultados'" placeholder="Empresa"
                                optionValue="bd_name" optionLabel="label" class="w-full pl-5 py-0 pr-0">
                            </Dropdown>
                        </IconField> -->

                        <IconField iconPosition="left" class="w-full mb-4">
                            <InputIcon class="pi pi-envelope" />
                            <InputText id="email" type="email" v-model="form.email" class="w-full"
                                placeholder="Correo electrónico" required autofocus autocomplete="off"
                                :invalid="form.errors.email ?? invalid" />
                        </IconField>
                        <span v-if="form.errors.email" class="-mt-3 mb-3 font-medium text-sm text-red-600">
                            {{ form.errors.email }}
                        </span>

                        <IconField iconPosition="left" class="w-full mb-4">
                            <InputIcon class="pi pi-lock z-2" />
                            <Password :inputId="'password'" type="password" v-model="form.password" class="w-full"
                                placeholder="Contraseña" required :inputStyle="{ paddingLeft: '2.5rem' }"
                                inputClass="w-full" toggleMask :feedback="false"
                                :invalid="form.errors.password ?? invalid">
                            </Password>
                        </IconField>
                        <span v-if="form.errors.password" class="-mt-3 mb-3 font-medium text-sm text-red-600">
                            {{ form.errors.password }}
                        </span>

                        <div class="mb-4 flex flex-wrap gap-3 ">
                            <div>
                                <Checkbox name="remember" v-model="form.remember" :value="form.remember" :binary="true"
                                    class="mr-2" :inputId="'remember'">
                                </Checkbox>
                                <label for="remember" class="text-900 font-medium"> Recordarme </label><!-- mr-8-->
                            </div>
                            <!-- <a class="text-600 hover:text-primary cursor-pointer ml-auto transition-colors transition-duration-300">Resetear Contraseña</a> -->
                        </div>
                        <Button label="Iniciar sesión" class="w-full" type="submit" :disabled="form.processing"
                            :class="{ 'opacity-25': form.processing }"></Button>
                        <!-- <div class="mt-4 text-center">
                            <a @click="navigateToRegister"
                                class="text-600 hover:text-primary cursor-pointer ml-auto transition-colors transition-duration-300 font-semibold">
                                Crear una cuenta
                            </a>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>
    </form>
    <!--AppConfig simple /-->
</template>
