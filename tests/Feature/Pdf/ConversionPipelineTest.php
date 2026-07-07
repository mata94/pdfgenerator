<?php

use App\Domain\Pdf\Enums\PdfJobStatus;
use App\Models\PdfJob;

dataset('successful_operations', [
    'PDF to Word' => ['pdf_to_word', fn () => fakePdfUpload(), 'PK'],
    'PDF to PPTX' => ['pdf_to_pptx', fn () => fakePdfUpload(), 'PK'],
    // PDF -> Excel goes PDF -> CSV (tabula) -> XLSX (LibreOffice). The
    // fixture PDF has no table, so tabula yields the placeholder CSV, but the
    // pipeline still produces a valid xlsx (a zip, hence the "PK" signature).
    'PDF to Excel' => ['pdf_to_excel', fn () => fakePdfUpload(), 'PK'],
    'PDF to JPG' => ['pdf_to_jpg', fn () => fakePdfUpload(), "\xFF\xD8\xFF"],
    'PDF to PNG' => ['pdf_to_png', fn () => fakePdfUpload(), "\x89PNG"],
    'PNG to PDF' => ['png_to_pdf', fn () => fakePngUpload(), '%PDF'],
    'Compress PDF' => ['compress', fn () => fakePdfUpload(), '%PDF'],
]);

test('conversion succeeds and produces a file with the right signature', function (string $operation, \Closure $file, string $expectedSignature) {
    ['jobId' => $jobId, 'response' => $convertResponse] = convertOperation($this, $operation, $file());

    $convertResponse->assertOk()->assertJson(['status' => 'completed']);

    $job = PdfJob::find($jobId);
    expect($job->status)->toBe(PdfJobStatus::COMPLETED->value);
    expect($job->output_file)->not->toBeNull();

    $download = $this->post("/api/v1/pdf/{$jobId}/download");
    $download->assertOk();
    expect(substr($download->streamedContent(), 0, strlen($expectedSignature)))->toBe($expectedSignature);
})->with('successful_operations');

test('convert 404s for a job that does not belong to anyone', function () {
    $this->postJson('/api/v1/pdf/convert', ['pdfJobId' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pdfJobId');
});

test('show 404s for a missing job', function () {
    $this->getJson('/api/v1/pdf/999999')
        ->assertStatus(404)
        ->assertJson(['error' => 'PDF job 999999 not found.']);
});

test('download 404s when the job has no output file yet', function () {
    $upload = $this->postJson('/api/v1/pdf/upload', [
        'file' => fakePdfUpload(),
        'operation' => 'compress',
    ])->assertOk();

    $this->post("/api/v1/pdf/{$upload->json('id')}/download")
        ->assertStatus(404)
        ->assertJson(['error' => 'File not available.']);
});
