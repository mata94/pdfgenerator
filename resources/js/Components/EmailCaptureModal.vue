<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submit', 'close']);

const email = ref('');

watch(() => props.visible, (visible) => {
    if (visible) {
        email.value = '';
    }
});

function submit() {
    if (email.value) {
        emit('submit', email.value);
    }
}
</script>

<template>
    <div v-if="visible" class="overlay" @click.self="emit('close')">
        <div class="modal">
            <h2>Enter your email to download</h2>
            <p class="subtitle">We'll use this to keep track of your free conversions.</p>

            <form @submit.prevent="submit">
                <input
                    v-model="email"
                    type="email"
                    required
                    placeholder="you@example.com"
                    autofocus
                />
                <div class="actions">
                    <button type="button" class="btn btn-outline" @click="emit('close')">Cancel</button>
                    <button type="submit" class="btn btn-filled" :disabled="busy">
                        {{ busy ? 'Please wait…' : 'Continue to download' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.overlay {
    position: fixed;
    inset: 0;
    background: rgba(27, 27, 24, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 16px;
}

.modal {
    width: 100%;
    max-width: 360px;
    background: var(--color-background-primary, #fff);
    border-radius: var(--border-radius-lg, 12px);
    padding: 28px;
}

h2 {
    font-size: 17px;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--color-text-primary, #1b1b18);
}

.subtitle {
    font-size: 13px;
    color: var(--color-text-secondary, #706f6c);
    margin-bottom: 20px;
}

input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid var(--color-border-tertiary, #ebebe9);
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 16px;
}

input:focus {
    outline: none;
    border-color: #E24B4A;
}

.actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.btn {
    font-size: 13px;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    border: none;
}

.btn-outline {
    background: transparent;
    color: var(--color-text-primary, #1b1b18);
    border: 1px solid var(--color-border-tertiary, #ebebe9);
}

.btn-filled {
    background: #E24B4A;
    color: #fff;
}

.btn-filled:hover {
    background: #C93B3A;
}

.btn-filled:disabled {
    opacity: 0.6;
    cursor: default;
}
</style>
