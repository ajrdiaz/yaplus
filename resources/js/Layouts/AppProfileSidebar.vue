<script setup>
import { ref, watch, computed } from 'vue';
import { useLayout } from '@/Layouts/composables/layout';
import { router } from '@inertiajs/vue3';

import Sidebar from "primevue/sidebar";
import Button from "primevue/button";

const { setScale, layoutConfig, layoutState } = useLayout();

// valores por default segun .env
layoutConfig.menuTheme.value = import.meta.env.VITE_APP_APOLLO_MENUTHEME || "colorScheme";
layoutConfig.scale.value = Number(import.meta.env.VITE_APP_APOLLO_SCALE || 10);

const scales = ref([10, 11, 12, 13, 14, 15, 16]);

const navigateToLogOut = () => {
    layoutState.profileSidebarVisible.value = false;
    router.post("/logout");
};

const changeColorScheme = (colorScheme) => {
    const themeLink = document.getElementById('theme-link');
    const themeLinkHref = themeLink.getAttribute('href');
    const currentColorScheme = 'theme-' + layoutConfig.colorScheme.value.toString();
    const newColorScheme = 'theme-' + colorScheme;
    const newHref = themeLinkHref.replace(currentColorScheme, newColorScheme);

    replaceLink(themeLink, newHref, () => {
        layoutConfig.colorScheme.value = colorScheme;
    });
};

/*
const colorScheme = ref(layoutConfig.colorScheme.value);
const changeColorScheme = (colorScheme) => {
    let themeHref;

    if (colorScheme === 'auto') {
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)').matches;
        colorScheme = prefersDarkScheme ? 'dim' : 'light';
    }

    if (colorScheme === 'light') {
        themeHref = 'theme-light.css';
    } else if (colorScheme === 'dim') {
        themeHref = 'theme-dark.css';
    } else {
        // Manejar otros esquemas de color si es necesario
        return;
    }

    const themeLink = document.getElementById('theme-link');
    themeLink.setAttribute('href', themeHref);
};
*/

const replaceLink = (linkElement, href, onComplete) => {
    if (!linkElement || !href) {
        return;
    }

    const id = linkElement.getAttribute('id');
    const cloneLinkElement = linkElement.cloneNode(true);

    cloneLinkElement.setAttribute('href', href);
    cloneLinkElement.setAttribute('id', id + '-clone');

    linkElement.parentNode.insertBefore(cloneLinkElement, linkElement.nextSibling);

    cloneLinkElement.addEventListener('load', () => {
        linkElement.remove();

        const element = document.getElementById(id); // re-check
        element && element.remove();

        cloneLinkElement.setAttribute('id', id);
        onComplete && onComplete();
    });
};

const decrementScale = () => {
    setScale(layoutConfig.scale.value - 1);
    applyScale();
};
const incrementScale = () => {
    setScale(layoutConfig.scale.value + 1);
    applyScale();
};
const applyScale = () => {
    document.documentElement.style.fontSize = layoutConfig.scale.value.toString() + 'px';
};
applyScale();
</script>

<template>
    <Sidebar v-model:visible="layoutState.profileSidebarVisible.value" position="right" class="layout-profile-sidebar w-full sm:w-25rem">
        <div class="flex flex-column mx-auto md:mx-0">
            <span class="mb-0 font-semibold">Bienvenido</span>
            <span class="text-color-secondary font-medium mb-3"> {{$page.props.auth.user.name}}</span>

            <ul class="list-none m-0 p-0">
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                        <span>
                            <i class="pi pi-user text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-1 font-semibold">Perfil</span>
                            <p class="text-color-secondary m-0">Muy pronto!</p>
                        </div>
                    </a>
                </li>
                <!-- <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                        <span>
                            <i class="pi pi-money-bill text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-2 font-semibold">Billing</span>
                            <p class="text-color-secondary m-0">Amet mimin mıollit</p>
                        </div>
                    </a>
                </li> -->
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                        <span>
                            <i class="pi pi-cog text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-1 font-semibold">Configuraciones</span>
                            <p class="text-color-secondary m-0">Muy pronto!</p>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150" @click="navigateToLogOut">
                        <span>
                            <i class="pi pi-power-off text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-1 font-semibold">Cerrar Sesión</span>
                            <p class="text-color-secondary m-0">Vuelve pronto!</p>
                        </div>
                    </a>
                </li>
            </ul>
        </div>

        <div class="flex flex-column mt-3 mx-auto md:mx-0">
            <span class="mb-3 font-semibold">Tamaño de Letra {{ typeof layoutConfig.scale.value }}</span>
            <div class="cursor-pointer flex surface-border mb-3 p-3 align-items-center justify-content-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                <Button icon="pi pi-minus" type="button" @click="decrementScale()" class="w-2rem h-2rem mr-2" text rounded :disabled="layoutConfig.scale.value === scales[0]"></Button>
                <div class="flex gap-2 align-items-center">
                    <i  class="pi pi-circle-fill text-300"
                        v-for="s in scales"
                        :key="s"
                        :class="{ 'text-primary-500': s === layoutConfig.scale.value }"></i>
                </div>
                <Button icon="pi pi-plus" type="button" @click="incrementScale()" class="w-2rem h-2rem ml-2" text rounded :disabled="layoutConfig.scale.value === scales[scales.length - 1]"></Button>
            </div>
        </div>

        <div class="flex flex-column mt-3 mx-auto md:mx-0">
            <span class="mb-0 font-semibold">Apariencia</span>
            <span class="text-color-secondary font-medium mb-3">Seleccione un tema</span>

            <ul class="list-none m-0 p-0">
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150" @click="changeColorScheme('light')">
                        <span>
                            <i class="pi pi-sun text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-0 font-semibold">Claro</span>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150" @click="changeColorScheme('dim')">
                        <span>
                            <i class="pi pi-moon text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-0 font-semibold">Oscuro</span>
                        </div>
                    </a>
                </li>
                <!--li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150" @click="changeColorScheme('auto')">
                        <span>
                            <i class="pi pi-sort text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-0 font-semibold">Automático</span>
                        </div>
                    </a>
                </li-->
            </ul>
        </div>
        <!-- <div class="flex flex-column mt-5 mx-auto md:mx-0">
            <span class="mb-2 font-semibold">Notifications</span>
            <span class="text-color-secondary font-medium mb-5">You have 3 notifications</span>

            <ul class="list-none m-0 p-0">
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                        <span>
                            <i class="pi pi-comment text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-2 font-semibold">Your post has new comments</span>
                            <p class="text-color-secondary m-0">5 min ago</p>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                        <span>
                            <i class="pi pi-trash text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-2 font-semibold">Your post has been deleted</span>
                            <p class="text-color-secondary m-0">15min ago</p>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                        <span>
                            <i class="pi pi-folder text-xl text-primary"></i>
                        </span>
                        <div class="ml-3">
                            <span class="mb-2 font-semibold">Post has been updated</span>
                            <p class="text-color-secondary m-0">3h ago</p>
                        </div>
                    </a>
                </li>
            </ul>
        </div>

        <div class="flex flex-column mt-5 mx-auto md:mx-0">
            <span class="mb-2 font-semibold">Messages</span>
            <span class="text-color-secondary font-medium mb-5">You have new messages</span>

            <ul class="list-none m-0 p-0">
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                        <span> <img src="/demo/images/avatar/circle/avatar-m-8.png" alt="Avatar" class="w-2rem h-2rem" /> </span>
                        <div class="ml-3">
                            <span class="mb-2 font-semibold">James Robinson</span>
                            <p class="text-color-secondary m-0">10 min ago</p>
                        </div>
                        <Badge value="3" class="ml-auto"></Badge>
                    </a>
                </li>
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                        <span> <img src="/demo/images/avatar/circle/avatar-f-8.png" alt="Avatar" class="w-2rem h-2rem" /> </span>
                        <div class="ml-3">
                            <span class="mb-2 font-semibold">Mary Watson</span>
                            <p class="text-color-secondary m-0">15min ago</p>
                        </div>
                        <Badge value="1" class="ml-auto"></Badge>
                    </a>
                </li>
                <li>
                    <a class="cursor-pointer flex surface-border mb-3 p-3 align-items-center border-1 surface-border border-round hover:surface-hover transition-colors transition-duration-150">
                        <span> <img src="/demo/images/avatar/circle/avatar-f-4.png" alt="Avatar" class="w-2rem h-2rem" /> </span>
                        <div class="ml-3">
                            <span class="mb-2 font-semibold">Aisha Webb</span>
                            <p class="text-color-secondary m-0">3h ago</p>
                        </div>
                        <Badge value="2" class="ml-auto"></Badge>
                    </a>
                </li>
            </ul>
        </div> -->
    </Sidebar>
</template>
