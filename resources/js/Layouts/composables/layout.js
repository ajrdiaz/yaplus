import { toRefs, reactive, computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';

const layoutConfig = reactive({
    menuMode: 'static',
    menuTheme: 'colorScheme',
    colorScheme: 'light',
    theme: 'indigo',
    scale: 0
});

const layoutState = reactive({
    staticMenuDesktopInactive: false,
    overlayMenuActive: false,
    sidebarActive: false,
    anchored: false,
    overlaySubmenuActive: false,
    profileSidebarVisible: false,
    configSidebarVisible: false,
    staticMenuMobileActive: false,
    menuHoverActive: false,
    activeMenuItem: null
});

const spinnerLoading = ref(false);

export function useLayout() {
    const toast = useToast();

    const showSuccess = (detail) => {
        toast.add({ severity: 'success', summary: 'Mensaje de éxito', detail: detail, life: 5000 });
    };

    const showInfo = (detail) => {
        toast.add({ severity: 'info', summary: 'Mensaje de información', detail: detail, life: 5000 });
    };

    const showWarn = (detail) => {
        toast.add({ severity: 'warn', summary: 'Mensaje de advertencia', detail: detail, life: 5000 });
    };

    const showError = (detail) => {
        toast.add({ severity: 'error', summary: 'Mensaje de error', detail: detail, life: 5000 });
    };

    const setScale = (scale) => {
        layoutConfig.scale = scale;
    };

    const setActiveMenuItem = (item) => {
        layoutState.activeMenuItem = item.value || item;
    };

    const onMenuToggle = () => {
        if (layoutConfig.menuMode === 'overlay') {
            layoutState.overlayMenuActive = !layoutState.overlayMenuActive;
        }

        if (window.innerWidth > 991) {
            layoutState.staticMenuDesktopInactive = !layoutState.staticMenuDesktopInactive;
        } else {
            layoutState.staticMenuMobileActive = !layoutState.staticMenuMobileActive;
        }
    };

    const onProfileSidebarToggle = () => {
        layoutState.profileSidebarVisible = !layoutState.profileSidebarVisible;
    };

    const onConfigSidebarToggle = () => {
        layoutState.configSidebarVisible = !layoutState.configSidebarVisible;
    };

    const isSidebarActive = computed(() => layoutState.overlayMenuActive || layoutState.staticMenuMobileActive || layoutState.overlaySubmenuActive);

    const isDarkTheme = computed(() => layoutConfig.darkTheme);

    const isDesktop = computed(() => window.innerWidth > 991);

    const isSlim = computed(() => layoutConfig.menuMode === 'slim');
    const isSlimPlus = computed(() => layoutConfig.menuMode === 'slim-plus');

    const isHorizontal = computed(() => layoutConfig.menuMode === 'horizontal');

    return {
        layoutConfig: toRefs(layoutConfig),
        layoutState: toRefs(layoutState),
        spinnerLoading,
        setScale,
        onMenuToggle,
        isSidebarActive,
        isDarkTheme,
        setActiveMenuItem,
        onProfileSidebarToggle,
        onConfigSidebarToggle,
        isSlim,
        isSlimPlus,
        isHorizontal,
        isDesktop,
        showSuccess,
        showInfo,
        showWarn,
        showError
    };
}
