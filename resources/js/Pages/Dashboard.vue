<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { findOperation } from '../pdfOperations';

defineProps({
    jobs: {
        type: Array,
        default: () => [],
    },
});

function operationLabel(value) {
    return findOperation(value)?.label ?? value;
}

function formatDate(iso) {
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <AppLayout>
        <section class="dashboard">
            <h1>Your conversions</h1>

            <p v-if="jobs.length === 0" class="empty">
                You haven't converted anything yet.
                <Link href="/">Start a conversion</Link>.
            </p>

            <div v-else class="job-list">
                <div v-for="job in jobs" :key="job.id" class="job-row">
                    <div class="job-info">
                        <span class="job-operation">{{ operationLabel(job.operation) }}</span>
                        <span class="job-date">{{ formatDate(job.createdAt) }}</span>
                    </div>

                    <div class="job-actions">
                        <span class="status" :class="job.status">{{ job.status }}</span>
                        <a v-if="job.downloadUrl" :href="job.downloadUrl" class="btn">Download</a>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.dashboard {
    max-width: 720px;
    margin: 0 auto;
    padding: 40px 24px;
}

h1 {
    font-size: 24px;
    font-weight: 500;
    color: var(--color-text-primary, #1b1b18);
    margin-bottom: 24px;
}

.empty {
    font-size: 14px;
    color: var(--color-text-secondary, #706f6c);
}

.job-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.job-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: var(--color-background-primary, #fff);
    border: 0.5px solid var(--color-border-tertiary, #ebebe9);
    border-radius: var(--border-radius-lg, 12px);
    padding: 14px 18px;
    flex-wrap: wrap;
}

.job-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.job-operation {
    font-size: 14px;
    font-weight: 500;
    color: var(--color-text-primary, #1b1b18);
}

.job-date {
    font-size: 12px;
    color: var(--color-text-secondary, #706f6c);
}

.job-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.status {
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 4px 10px;
    border-radius: 999px;
    background: #f2f2f2;
    color: var(--color-text-secondary, #706f6c);
}

.status.completed {
    background: #EAF3DE;
    color: #3B6D11;
}

.status.failed {
    background: #FCEBEB;
    color: #E24B4A;
}

.status.processing,
.status.pending {
    background: #FAEEDA;
    color: #854F0B;
}

.btn {
    font-size: 13px;
    font-weight: 500;
    padding: 6px 14px;
    border-radius: 8px;
    background: #E24B4A;
    color: #fff;
    text-decoration: none;
}

.btn:hover {
    background: #C93B3A;
}

@media (max-width: 480px) {
    .dashboard {
        padding: 24px 16px;
    }

    .job-row {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
