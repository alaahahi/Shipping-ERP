import { onMounted, ref } from 'vue';
import { applyTheme, persistTheme, readStoredTheme } from '@/theme';

const theme = ref(readStoredTheme());

export function useTheme() {
    const sync = () => {
        theme.value = readStoredTheme();
        applyTheme(theme.value);
    };

    const toggleTheme = () => {
        theme.value = persistTheme(theme.value === 'dark' ? 'light' : 'dark');
    };

    onMounted(sync);

    return {
        theme,
        toggleTheme,
        sync,
    };
}
