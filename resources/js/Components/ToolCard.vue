<script setup>
import { computed } from 'vue';
import { FILE_TYPE_STYLES } from '../pdfOperations';

const props = defineProps({
    operation: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['select']);

const style = computed(() => FILE_TYPE_STYLES[props.operation.fileType]);

function select() {
    if (!props.busy) {
        emit('select', props.operation);
    }
}
</script>

<template>
    <button type="button" class="tool-card" :class="{ selected }" :disabled="busy" @click="select">
        <span class="icon" :style="{ background: style.bg, color: style.color }">
            <svg v-if="operation.fileType === 'photo'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <circle cx="9" cy="9" r="2" />
                <path d="m21 15-5-5L5 21" />
            </svg>
            <svg v-else-if="operation.fileType === 'excel'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <path d="M3 9h18M3 15h18M9 3v18M15 3v18" />
            </svg>
            <svg v-else-if="operation.fileType === 'pptx'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="13" rx="2" />
                <path d="M8 21h8M12 17v4" />
            </svg>
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <path d="M14 2v6h6M9 13h6M9 17h6" />
            </svg>
        </span>

        <span class="name">{{ operation.label }}</span>
        <span class="description">{{ operation.description }}</span>
    </button>
</template>

<style scoped>
.tool-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    background: var(--color-background-primary, #fff);
    border: 0.5px solid var(--color-border-tertiary, #ebebe9);
    border-radius: var(--border-radius-lg, 12px);
    padding: 14px 16px;
    text-align: left;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}

.tool-card:hover {
    border-color: #E24B4A;
    background: #FEF9F9;
}

/* Selected: red border highlight without a layout shift (box-shadow). */
.tool-card.selected {
    border-color: #E24B4A;
    background: #FEF3F3;
    box-shadow: 0 0 0 1px #E24B4A;
}

.tool-card:disabled {
    opacity: 0.6;
    cursor: default;
}

.icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.name {
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-primary, #1b1b18);
}

.description {
    font-size: 11px;
    color: var(--color-text-secondary, #706f6c);
}
</style>
