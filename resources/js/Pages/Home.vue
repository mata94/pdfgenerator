<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import UploadZone from '../Components/UploadZone.vue';
import ToolCard from '../Components/ToolCard.vue';
import EmailCaptureModal from '../Components/EmailCaptureModal.vue';
import GuestLimitBanner from '../Components/GuestLimitBanner.vue';
import { PDF_OPERATIONS, findOperation, operationForFile } from '../pdfOperations';
import { useConversionFlow } from '../useConversionFlow';
import { saveGuestEmail, downloadJob, triggerBlobDownload } from '../pdfApi';

const page = usePage();
const { status, job, errorCode, errorMessage, run, reset, fail } = useConversionFlow();

const selectedOperation = ref(null);
const pendingFile = ref(null);

const showEmailModal = ref(false);
const emailBusy = ref(false);
const downloadError = ref('');
const guestLimitReached = ref(false);

const isGuest = computed(() => !page.props.auth?.user);
const isBusy = computed(() => status.value === 'uploading' || status.value === 'converting');
const currentOperationLabel = computed(() => (job.value ? findOperation(job.value.operation)?.label : ''));

const heroTitle = computed(() => selectedOperation.value?.label ?? 'Convert, compress, and manage your PDFs');
const heroSubtitle = computed(() => selectedOperation.value?.description ?? 'Fast, free, and no installation needed.');

const showUploadZone = computed(
    () => !guestLimitReached.value && (status.value === 'idle' || status.value === 'error')
);

function selectTool(operation) {
    selectedOperation.value = operation;
    pendingFile.value = null;
    downloadError.value = '';
    guestLimitReached.value = false;
    reset();

    // The tool grid lives below the hero, so bring the (now context-aware)
    // upload zone back into view after a card is picked.
    if (typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function handleFilePicked(file) {
    downloadError.value = '';
    guestLimitReached.value = false;
    reset();

    // No tool chosen up front (default drag/drop flow) — infer it from the
    // file's extension so the user still sees what will happen before
    // committing to the conversion.
    if (!selectedOperation.value) {
        const operationValue = operationForFile(file);
        if (!operationValue) {
            fail('Unsupported file type. Try PDF, Word, Excel, PowerPoint, JPG, or PNG.');
            return;
        }
        selectedOperation.value = findOperation(operationValue);
    }

    pendingFile.value = file;
}

async function startConversion() {
    if (!pendingFile.value || !selectedOperation.value) {
        return;
    }

    downloadError.value = '';
    guestLimitReached.value = false;

    await run(pendingFile.value, selectedOperation.value.value);

    if (errorCode.value === 'guest_limit_reached') {
        guestLimitReached.value = true;
    }
}

function startOver() {
    reset();
    selectedOperation.value = null;
    pendingFile.value = null;
    downloadError.value = '';
    guestLimitReached.value = false;
}

function startDownload() {
    downloadError.value = '';

    if (isGuest.value) {
        showEmailModal.value = true;
        return;
    }

    performDownload();
}

async function submitEmail(email) {
    emailBusy.value = true;

    try {
        await saveGuestEmail(email);
        showEmailModal.value = false;
        await performDownload();
    } catch (error) {
        downloadError.value = error.response?.data?.message ?? 'Could not save your email.';
    } finally {
        emailBusy.value = false;
    }
}

async function performDownload() {
    try {
        const { blob, filename } = await downloadJob(job.value.id);
        triggerBlobDownload(blob, filename);
    } catch (error) {
        if (error.response?.data?.error === 'guest_limit_reached') {
            guestLimitReached.value = true;
        }
        downloadError.value = error.response?.data?.message ?? 'Download failed.';
    }
}
</script>

<template>
    <AppLayout>
        <section class="hero">
            <h1>{{ heroTitle }}</h1>
            <p class="subtitle">{{ heroSubtitle }}</p>

            <template v-if="showUploadZone">
                <UploadZone
                    :operation="selectedOperation"
                    :selected-file="pendingFile"
                    :busy="isBusy"
                    @file="handleFilePicked"
                    @convert="startConversion"
                />

                <button v-if="selectedOperation" type="button" class="clear-link" @click="startOver">
                    Choose a different tool
                </button>

                <p v-if="status === 'error'" class="error">{{ errorMessage }}</p>
            </template>

            <p v-if="isGuest && !guestLimitReached && status === 'idle'" class="guest-note">
                <span class="dot" />You can use 3 times without logging in
            </p>

            <GuestLimitBanner v-if="guestLimitReached" />

            <div v-else-if="isBusy || status === 'completed'" class="result-panel">
                <p v-if="status === 'uploading'">Uploading…</p>
                <p v-else-if="status === 'converting'">Converting…</p>
                <template v-else-if="status === 'completed'">
                    <p class="result-title">{{ currentOperationLabel }} complete.</p>
                    <div class="result-actions">
                        <button type="button" class="btn btn-filled" @click="startDownload">Download</button>
                        <button type="button" class="btn btn-outline" @click="startOver">Start over</button>
                    </div>
                    <p v-if="downloadError" class="error">{{ downloadError }}</p>
                </template>
            </div>
        </section>

        <section class="tools">
            <div class="tools-container">
                <h2 class="section-title">All tools</h2>
                <div class="tool-grid">
                    <ToolCard
                        v-for="operation in PDF_OPERATIONS"
                        :key="operation.value"
                        :operation="operation"
                        :selected="selectedOperation?.value === operation.value"
                        :busy="isBusy"
                        @select="selectTool"
                    />
                </div>
            </div>
        </section>

        <EmailCaptureModal
            :visible="showEmailModal"
            :busy="emailBusy"
            @submit="submitEmail"
            @close="showEmailModal = false"
        />
    </AppLayout>
</template>

<style scoped>
.hero {
    background: #FEF9F9;
    padding: 56px 24px 40px;
    text-align: center;
}

h1 {
    font-size: 32px;
    font-weight: 500;
    letter-spacing: -0.5px;
    color: var(--color-text-primary, #1b1b18);
    max-width: 640px;
    margin: 0 auto 8px;
}

.subtitle {
    font-size: 15px;
    color: var(--color-text-secondary, #706f6c);
    margin-bottom: 32px;
}

.clear-link {
    display: block;
    margin: 14px auto 0;
    background: transparent;
    border: none;
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-secondary, #706f6c);
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.clear-link:hover {
    color: #E24B4A;
}

.guest-note {
    margin-top: 16px;
    font-size: 13px;
    color: var(--color-text-secondary, #706f6c);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #3B6D11;
    display: inline-block;
}

.result-panel {
    max-width: 560px;
    margin: 16px auto 0;
    background: var(--color-background-primary, #fff);
    border: 0.5px solid var(--color-border-tertiary, #ebebe9);
    border-radius: var(--border-radius-lg, 12px);
    padding: 20px;
}

.result-title {
    font-size: 14px;
    font-weight: 500;
    color: var(--color-text-primary, #1b1b18);
    margin-bottom: 12px;
}

.result-actions {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.btn {
    font-size: 13px;
    font-weight: 500;
    padding: 8px 18px;
    border-radius: 8px;
    cursor: pointer;
    border: none;
}

.btn-filled {
    background: #E24B4A;
    color: #fff;
}

.btn-filled:hover {
    background: #C93B3A;
}

.btn-outline {
    background: transparent;
    color: var(--color-text-primary, #1b1b18);
    border: 1px solid var(--color-border-tertiary, #ebebe9);
}

.error {
    margin-top: 12px;
    font-size: 13px;
    color: #E24B4A;
}

.tools {
    padding: 40px 0;
}

.tools-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 32px;
}

.section-title {
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-secondary, #706f6c);
    margin-bottom: 16px;
}

/* 6 cards per row on desktop, 3 on tablet, 2 on mobile. */
.tool-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
}

@media (max-width: 1024px) {
    .tool-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 640px) {
    .tool-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .hero {
        padding: 40px 16px 32px;
    }

    h1 {
        font-size: 24px;
    }

    .tools-container {
        padding: 0 16px;
    }

    .result-actions {
        flex-direction: column;
    }

    .result-actions .btn {
        width: 100%;
    }
}
</style>
