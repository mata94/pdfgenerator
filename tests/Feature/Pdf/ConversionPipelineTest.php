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

test('rotate succeeds and produces a valid rotated pdf', function () {
    ['jobId' => $jobId, 'response' => $convertResponse] = convertOperation(
        $this,
        'rotate',
        fakePdfUpload(),
        ['angle' => 90],
    );

    $convertResponse->assertOk()->assertJson(['status' => 'completed']);

    $job = PdfJob::find($jobId);
    expect($job->status)->toBe(PdfJobStatus::COMPLETED->value);
    expect($job->options)->toBe(['angle' => 90]);

    $download = $this->post("/api/v1/pdf/{$jobId}/download");
    $download->assertOk();
    expect(substr($download->streamedContent(), 0, 4))->toBe('%PDF');
});

test('rotate requires a valid angle', function () {
    $this->postJson('/api/v1/pdf/upload', [
        'file' => fakePdfUpload(),
        'operation' => 'rotate',
        'options' => ['angle' => 45],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('options.angle');
});

test('protect succeeds and produces a password-encrypted pdf', function () {
    ['jobId' => $jobId, 'response' => $convertResponse] = convertOperation(
        $this,
        'protect',
        fakePdfUpload(),
        ['password' => 'sekret123'],
    );

    $convertResponse->assertOk()->assertJson(['status' => 'completed']);

    $job = PdfJob::find($jobId);
    expect($job->status)->toBe(PdfJobStatus::COMPLETED->value);

    $download = $this->post("/api/v1/pdf/{$jobId}/download");
    $download->assertOk();

    $tmpPath = tempnam(sys_get_temp_dir(), 'protected').'.pdf';
    file_put_contents($tmpPath, $download->streamedContent());

    // Wrong/no password must fail to decrypt — proves the file is genuinely encrypted.
    exec('qpdf --decrypt '.escapeshellarg($tmpPath).' '.escapeshellarg($tmpPath.'.nopw').' 2>&1', result_code: $noPasswordExit);
    expect($noPasswordExit)->toBe(2);

    // The right password must decrypt successfully (exit 0, or 3 for warnings-only).
    exec('qpdf --password=sekret123 --decrypt '.escapeshellarg($tmpPath).' '.escapeshellarg($tmpPath.'.right').' 2>&1', result_code: $rightPasswordExit);
    expect($rightPasswordExit)->toBeIn([0, 3]);

    @unlink($tmpPath);
    @unlink($tmpPath.'.nopw');
    @unlink($tmpPath.'.right');
});

test('protect requires a password', function () {
    $this->postJson('/api/v1/pdf/upload', [
        'file' => fakePdfUpload(),
        'operation' => 'protect',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('options.password');
});

test('unlock succeeds and produces a decrypted pdf', function () {
    ['jobId' => $jobId, 'response' => $convertResponse] = convertOperation(
        $this,
        'unlock',
        encryptedPdfUpload('sekret123'),
        ['password' => 'sekret123'],
    );

    $convertResponse->assertOk()->assertJson(['status' => 'completed']);

    $job = PdfJob::find($jobId);
    expect($job->status)->toBe(PdfJobStatus::COMPLETED->value);

    $download = $this->post("/api/v1/pdf/{$jobId}/download");
    $download->assertOk();

    $tmpPath = tempnam(sys_get_temp_dir(), 'unlocked').'.pdf';
    file_put_contents($tmpPath, $download->streamedContent());

    exec('qpdf --show-encryption '.escapeshellarg($tmpPath).' 2>&1', $output);
    @unlink($tmpPath);

    expect(implode("\n", $output))->toContain('File is not encrypted');
});

test('unlock fails clearly with the wrong password', function () {
    ['jobId' => $jobId, 'response' => $convertResponse] = convertOperation(
        $this,
        'unlock',
        encryptedPdfUpload('sekret123'),
        ['password' => 'totally-wrong'],
    );

    $convertResponse->assertStatus(422)->assertJson([
        'error' => 'Wrong password — could not unlock the PDF.',
    ]);

    $job = PdfJob::find($jobId);
    expect($job->status)->toBe(PdfJobStatus::FAILED->value);
});

test('unlock requires a password', function () {
    $this->postJson('/api/v1/pdf/upload', [
        'file' => fakePdfUpload(),
        'operation' => 'unlock',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('options.password');
});

test('watermark succeeds and stamps the text onto the pdf', function () {
    ['jobId' => $jobId, 'response' => $convertResponse] = convertOperation(
        $this,
        'watermark',
        fakePdfUpload(),
        ['text' => 'CONFIDENTIAL'],
    );

    $convertResponse->assertOk()->assertJson(['status' => 'completed']);

    $job = PdfJob::find($jobId);
    expect($job->status)->toBe(PdfJobStatus::COMPLETED->value);

    $download = $this->post("/api/v1/pdf/{$jobId}/download");
    $download->assertOk();
    expect(substr($download->streamedContent(), 0, 4))->toBe('%PDF');

    // The watermark text lives inside a compressed content stream, so a raw
    // byte search won't find it — decompress via qpdf's QDF mode first.
    $tmpPath = tempnam(sys_get_temp_dir(), 'watermarked').'.pdf';
    file_put_contents($tmpPath, $download->streamedContent());
    exec('qpdf --qdf --object-streams=disable '.escapeshellarg($tmpPath).' - 2>/dev/null', $output);
    @unlink($tmpPath);

    expect(implode("\n", $output))->toContain('CONFIDENTIAL');
});

test('watermark requires text', function () {
    $this->postJson('/api/v1/pdf/upload', [
        'file' => fakePdfUpload(),
        'operation' => 'watermark',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('options.text');
});

test('watermark cleans up its temporary stamp file', function () {
    ['jobId' => $jobId] = convertOperation($this, 'watermark', fakePdfUpload(), ['text' => 'DRAFT']);

    $job = PdfJob::find($jobId);
    $stampPath = Illuminate\Support\Facades\Storage::disk('local')->path($job->output_file).'.stamp.pdf';

    expect(file_exists($stampPath))->toBeFalse();
});

test('ocr succeeds and embeds a searchable text layer', function () {
    ['jobId' => $jobId, 'response' => $convertResponse] = convertOperation(
        $this,
        'ocr',
        scannedPdfUpload('HELLO WATERMARK'),
    );

    $convertResponse->assertOk()->assertJson(['status' => 'completed']);

    $job = PdfJob::find($jobId);
    expect($job->status)->toBe(PdfJobStatus::COMPLETED->value);

    $download = $this->post("/api/v1/pdf/{$jobId}/download");
    $download->assertOk();

    $tmpPath = tempnam(sys_get_temp_dir(), 'ocr_out').'.pdf';
    file_put_contents($tmpPath, $download->streamedContent());

    // OCR's text layer uses a subsetted font with remapped glyph codes, so it
    // can't be grepped directly. Instead, re-run ocrmypdf --skip-text on the
    // result: it only reports "skipping" when the page already has text.
    exec('ocrmypdf --skip-text -l eng '.escapeshellarg($tmpPath).' '.escapeshellarg($tmpPath.'.reocr').' 2>&1', $output);
    @unlink($tmpPath);
    @unlink($tmpPath.'.reocr');

    expect(implode("\n", $output))->toContain('skipping all processing');
});

test('ocr on an already-text pdf completes without error', function () {
    ['response' => $convertResponse] = convertOperation($this, 'ocr', fakePdfUpload());

    $convertResponse->assertOk()->assertJson(['status' => 'completed']);
});

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
