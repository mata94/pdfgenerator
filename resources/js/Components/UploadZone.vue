<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    // The selected tool's operation object, or null in the default state.
    operation: {
        type: Object,
        default: null,
    },
    // A file the user has picked but not yet converted, or null.
    selectedFile: {
        type: Object,
        default: null,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['file', 'convert']);

const GENERAL_ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png';

const isDragging = ref(false);
const fileInput = ref(null);

const acceptAttr = computed(() => props.operation?.accept ?? GENERAL_ACCEPT);

const acceptedFormatsLabel = computed(() => {
    if (!props.operation) {
        return '';
    }

    return props.operation.accept
        .split(',')
        .map((ext) => ext.replace('.', '').toUpperCase())
        .join(', ');
});

function openFilePicker() {
    if (!props.busy) {
        fileInput.value?.click();
    }
}

function onDragOver() {
    if (!props.busy) {
        isDragging.value = true;
    }
}

function onDragLeave() {
    isDragging.value = false;
}

function onDrop(event) {
    isDragging.value = false;
    if (props.busy) {
        return;
    }
    const file = event.dataTransfer?.files?.[0];
    if (file) {
        emit('file', file);
    }
}

function onFileChange(event) {
    const file = event.target.files?.[0];
    if (file) {
        emit('file', file);
    }
    event.target.value = '';
}

function convert() {
    if (!props.busy) {
        emit('convert');
    }
}
</script>

<template>
    <div
        class="upload-zone"
        :class="{ dragging: isDragging, busy }"
        @dragover.prevent="onDragOver"
        @dragleave.prevent="onDragLeave"
        @drop.prevent="onDrop"
    >
        <input
            ref="fileInput"
            type="file"
            class="hidden-input"
            :accept="acceptAttr"
            @change="onFileChange"
        />

        <div class="upload-icon">
            <svg v-if="selectedFile" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <path d="M14 2v6h6" />
            </svg>
            <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 16V4M12 4L7 9M12 4l5 5" />
                <path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" />
            </svg>
        </div>

        <!-- State 3: a file has been picked, ready to convert -->
        <template v-if="selectedFile">
            <p class="title filename">{{ selectedFile.name }}</p>
            <p class="subtitle">
                <template v-if="operation">Ready to convert — {{ operation.label }}</template>
                <template v-else>Ready to convert</template>
            </p>
            <div class="actions">
                <button type="button" class="primary-btn" :disabled="busy" @click="convert">Convert</button>
                <button type="button" class="ghost-btn" :disabled="busy" @click="openFilePicker">Choose another file</button>
            </div>
        </template>

        <!-- State 2: a tool is selected, waiting for a file -->
        <template v-else-if="operation">
            <p class="title">Upload file for {{ operation.label }}</p>
            <p class="subtitle">Accepted formats: {{ acceptedFormatsLabel }}</p>
            <button type="button" class="primary-btn" :disabled="busy" @click="openFilePicker">Upload file</button>
        </template>

        <!-- State 1: default, no tool selected -->
        <template v-else>
            <p class="title">Drag PDF here or select a file</p>
            <p class="subtitle">Supports PDF, Word, Excel, PowerPoint, JPG, PNG</p>
            <button type="button" class="primary-btn" :disabled="busy" @click="openFilePicker">Select file</button>
        </template>
    </div>
</template>

<style scoped>
.upload-zone {
    max-width: 560px;
    margin: 0 auto;
    border: 1.5px dashed #E24B4A;
    border-radius: var(--border-radius-lg, 12px);
    padding: 36px 24px;
    background: var(--color-background-primary, #fff);
    text-align: center;
    transition: background 0.15s ease;
}

.upload-zone.dragging {
    background: #FEF3F3;
}

.upload-zone.busy {
    opacity: 0.7;
}

.hidden-input {
    display: none;
}

.upload-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #FCEBEB;
    color: #E24B4A;
    border-radius: 10px;
}

.title {
    font-size: 15px;
    font-weight: 500;
    color: var(--color-text-primary, #1b1b18);
    margin-bottom: 4px;
}

.filename {
    word-break: break-all;
}

.subtitle {
    font-size: 13px;
    color: var(--color-text-secondary, #706f6c);
    margin-bottom: 16px;
}

.actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.primary-btn {
    background: #E24B4A;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 20px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

.primary-btn:hover {
    background: #C93B3A;
}

.primary-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

.ghost-btn {
    background: transparent;
    color: var(--color-text-secondary, #706f6c);
    border: none;
    padding: 8px 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.ghost-btn:disabled {
    opacity: 0.6;
    cursor: default;
}
</style>
