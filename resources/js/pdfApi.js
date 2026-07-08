import { api, ensureCsrfCookie } from './bootstrap';

export async function uploadFile(file, operation, options = null) {
    await ensureCsrfCookie();

    const formData = new FormData();
    formData.append('file', file);
    formData.append('operation', operation);

    if (options) {
        Object.entries(options).forEach(([key, value]) => {
            formData.append(`options[${key}]`, value);
        });
    }

    const response = await api.post(route('api.v1.pdf.upload'), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return response.data;
}

export async function convertJob(jobId) {
    await ensureCsrfCookie();

    const response = await api.post(route('api.v1.pdf.convert'), { pdfJobId: jobId });

    return response.data;
}

export async function getJob(jobId) {
    const response = await api.get(route('api.v1.pdf.show', jobId));

    return response.data;
}

export async function saveGuestEmail(email) {
    await ensureCsrfCookie();

    const response = await api.post(route('api.v1.guest.email'), { email });

    return response.data;
}

export async function downloadJob(jobId) {
    await ensureCsrfCookie();

    const response = await api.post(
        route('api.v1.pdf.download', jobId),
        {},
        { responseType: 'blob' },
    );

    const disposition = response.headers['content-disposition'] ?? '';
    const match = disposition.match(/filename="?([^"]+)"?/);

    return {
        blob: response.data,
        filename: match ? match[1] : `download-${jobId}`,
    };
}

export function triggerBlobDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}
