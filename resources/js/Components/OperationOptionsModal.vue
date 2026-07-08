<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    operation: {
        type: Object,
        default: null,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submit', 'close']);

const values = reactive({});

function defaultFor(field) {
    if (field.type === 'select') {
        return field.options?.[0]?.value ?? '';
    }

    return '';
}

watch(
    () => props.visible,
    (visible) => {
        if (!visible || !props.operation?.optionsSchema) {
            return;
        }

        Object.keys(values).forEach((key) => delete values[key]);
        props.operation.optionsSchema.forEach((field) => {
            values[field.key] = defaultFor(field);
        });
    },
);

function submit() {
    emit('submit', { ...values });
}
</script>

<template>
    <div v-if="visible && operation" class="overlay" @click.self="emit('close')">
        <div class="modal">
            <h2>{{ operation.label }} options</h2>

            <form @submit.prevent="submit">
                <div v-for="field in operation.optionsSchema" :key="field.key" class="field">
                    <label :for="`option-${field.key}`">{{ field.label }}</label>

                    <select
                        v-if="field.type === 'select'"
                        :id="`option-${field.key}`"
                        v-model="values[field.key]"
                        :required="field.required"
                    >
                        <option v-for="opt in field.options" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>

                    <input
                        v-else-if="field.type === 'password'"
                        :id="`option-${field.key}`"
                        v-model="values[field.key]"
                        type="password"
                        :required="field.required"
                        :placeholder="field.placeholder"
                    />

                    <input
                        v-else
                        :id="`option-${field.key}`"
                        v-model="values[field.key]"
                        type="text"
                        :required="field.required"
                        :placeholder="field.placeholder"
                    />
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-outline" @click="emit('close')">Cancel</button>
                    <button type="submit" class="btn btn-filled" :disabled="busy">
                        {{ busy ? 'Please wait…' : 'Continue' }}
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
    margin-bottom: 20px;
    color: var(--color-text-primary, #1b1b18);
}

.field {
    margin-bottom: 16px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-primary, #1b1b18);
    margin-bottom: 6px;
}

select,
input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid var(--color-border-tertiary, #ebebe9);
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
}

select:focus,
input:focus {
    outline: none;
    border-color: #E24B4A;
}

.actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 4px;
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
