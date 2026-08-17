import { nextTick, reactive } from 'vue';

const ACTION_PIN = String(import.meta.env.VITE_ACTION_PIN || '12457');
export const ACTION_PIN_LENGTH = ACTION_PIN.length;

const state = reactive({
    open: false,
    message: '',
    pin: '',
    error: false,
    shaking: false,
});

let resolver = null;
let otpApi = null;
let shakeTimer = null;
let checking = false;

function settle(result) {
    const resolve = resolver;
    resolver = null;
    checking = false;
    if (shakeTimer) {
        clearTimeout(shakeTimer);
        shakeTimer = null;
    }
    state.open = false;
    state.pin = '';
    state.error = false;
    state.shaking = false;
    state.message = '';
    resolve?.(result);
}

function playShake() {
    state.shaking = false;
    nextTick(() => {
        state.shaking = true;
        if (shakeTimer) {
            clearTimeout(shakeTimer);
        }
        shakeTimer = setTimeout(() => {
            state.shaking = false;
            shakeTimer = null;
        }, 450);
    });
}

function resetOtp() {
    nextTick(() => {
        otpApi?.clearInput?.();
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
            state.shaking = false;
            state.open = true;
        });
    };

    const setOtpApi = (api) => {
        otpApi = api;
    };

    const submitPin = (value = state.pin) => {
        const pin = String(value ?? '').trim();
        if (checking || pin.length !== ACTION_PIN_LENGTH) {
            return;
        }

        checking = true;

        if (pin === ACTION_PIN) {
            settle(true);
            return;
        }

        state.error = true;
        state.pin = '';
        playShake();
        resetOtp();
        nextTick(() => {
            checking = false;
        });
    };

    const onPinChange = (value) => {
        state.pin = value ?? '';
        if (state.error && state.pin) {
            state.error = false;
        }
    };

    const cancelPin = () => {
        settle(false);
    };

    return {
        state,
        pinLength: ACTION_PIN_LENGTH,
        requireActionPin,
        submitPin,
        cancelPin,
        setOtpApi,
        onPinChange,
    };
}
