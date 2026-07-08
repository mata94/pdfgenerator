import { ref } from 'vue';
import { uploadFile, convertJob } from './pdfApi';

export function useConversionFlow() {
    const status = ref('idle'); // idle | uploading | converting | completed | error
    const job = ref(null);
    const errorCode = ref('');
    const errorMessage = ref('');

    async function run(file, operation, options = null) {
        status.value = 'uploading';
        errorCode.value = '';
        errorMessage.value = '';
        job.value = null;

        try {
            const uploaded = await uploadFile(file, operation, options);
            status.value = 'converting';
            job.value = await convertJob(uploaded.id);
            status.value = 'completed';
        } catch (error) {
            status.value = 'error';
            errorCode.value = error.response?.data?.error ?? '';
            errorMessage.value = error.response?.data?.message
                ?? error.response?.data?.error
                ?? 'Something went wrong. Please try again.';
        }
    }

    function reset() {
        status.value = 'idle';
        job.value = null;
        errorCode.value = '';
        errorMessage.value = '';
    }

    function fail(message) {
        status.value = 'error';
        errorCode.value = '';
        errorMessage.value = message;
    }

    return { status, job, errorCode, errorMessage, run, reset, fail };
}
