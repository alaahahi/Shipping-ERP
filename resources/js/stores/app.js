import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useAppStore = defineStore('app', () => {
    // Start closed so mobile does not overlay on first paint.
    // Desktop sidebar remains visible via CSS grid.
    const sidebarCollapsed = ref(true);

    function toggleSidebar() {
        sidebarCollapsed.value = !sidebarCollapsed.value;
    }

    function openSidebar() {
        sidebarCollapsed.value = false;
    }

    function closeSidebar() {
        sidebarCollapsed.value = true;
    }

    return {
        sidebarCollapsed,
        toggleSidebar,
        openSidebar,
        closeSidebar,
    };
});
