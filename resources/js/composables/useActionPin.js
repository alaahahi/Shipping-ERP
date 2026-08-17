import { nextTick, reactive } from 'vue';

const ACTION_PIN = String(import.meta.env.VITE_ACTION_PIN || '12457');

const state = reactive({
    open: false,
    message: '',
    pin: '',
    error: false,
});

let resolver = null;
let inputEl = null;

function settle(result) {
    const resolve = resolver;
    resolver = null;
    state.open = false;
    state.pin = '';
    state.error = false;
    state.message = '';
    resolve?.(result);
}

function focusInput() {
    nextTick(() => {
        inputEl?.focus?.();
        inputEl?.select?.();
    });
}

export function useActionPin() {
    const requireActionPin = (message = '') => {
        if (resolver) {
            settle(false);
        }

        return new Promise((resolve) => {
            resolver = resolve;
            state.message = message || '';
            state.pin = '';
            state.error = false;
            state.open = true;
            focusInput();
        });
    };

    const setInputEl = (el) => {
        inputEl = el;
        if (el && state.open) {
            focusInput();
        }
    };

    const submitPin = () => {
        if (String(state.pin).trim() === ACTION_PIN) {
            settle(true);
            return;
        }

        state.error = true;
        state.pin = '';
        focusInput();
    };

    const cancelPin = () => {
        settle(false);
    };

    return {
        state,
        requireActionPin,
        submitPin,
        cancelPin,
        setInputEl,
    };
}
