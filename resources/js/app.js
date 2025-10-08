import './bootstrap';
//import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import PrimeVue from "primevue/config";
import ConfirmationService from "primevue/confirmationservice";
import DialogService from "primevue/dialogservice";
import ToastService from "primevue/toastservice";

import Tooltip from "primevue/tooltip";
import BadgeDirective from "primevue/badgedirective";
import Ripple from "primevue/ripple";
import StyleClass from "primevue/styleclass";

import AppLayout from "@/Layouts/AppLayout.vue";

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

import "@/Assets/styles.scss";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    //resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        );
        if (page.default.layout === undefined) page.default.layout = AppLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                ripple: true,
                locale: {
                    accept: 'Si',
                    reject: 'No',
                    choose: 'Elegir',
                    upload: 'Subir',
                    cancel: 'Cancelar',
                    completed: 'Completado',
                    pending: 'Pendiente',
                    dayNames: ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'],
                    dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'],
                    dayNamesMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                    monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                    monthNamesShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'],
                    chooseYear: 'Elija Anio',
                    chooseMonth: 'Elija Mes',
                    chooseDate: 'Elija Fecha',
                    prevDecade: 'Decada Anterior',
                    nextDecade: 'Decada Siguiente',
                    prevYear: 'Anio Anterior',
                    nextYear: 'Anio Siguiente',
                    prevMonth: 'Mes Anterior',
                    nextMonth: 'Mes Siguiente',
                    prevHour: 'Hora Anterior',
                    nextHour: 'Hora Siguiente',
                    prevMinute: 'Minuto Anterior',
                    nextMinute: 'Minuto Siguiente',
                    prevSecond: 'Segundo Anterior',
                    nextSecond: 'Segundo Siguiente',
                    am: 'AM',
                    pm: 'PM',
                    today: 'Hoy',
                    weekHeader: 'Sem',
                    firstDayOfWeek: 0,
                    showMonthAfterYear: false,
                    // dateFormat: 'dd/mm/yy',
                    weak: 'Debil',
                    medium: 'Medio',
                    strong: 'Fuerte',
                    passwordPrompt: 'Ingrese una contraseña',
                    searchMessage: "{0} resultados disponibles",
                    selectionMessage: "{0} selecciones",
                    emptySelectionMessage: 'No hay seleccionado',
                    emptySearchMessage: 'No se encontraron resultados',
                    fileChosenMessage: "{0} archivo(s)",
                    noFileChosenMessage: "Ningun archivo seleccionado",
                    emptyMessage: 'No hay opciones disponibles',
                    // https://primevue.org/configuration/
                }
            })

            .use(ToastService)
            .use(DialogService)
            .use(ConfirmationService)

            .directive("tooltip", Tooltip)
            .directive("badge", BadgeDirective)
            .directive("ripple", Ripple)
            .directive("styleclass", StyleClass)

            .mount(el);
    },
    progress: {
        color: '#009b72',
        delay: 0,
    },
});
