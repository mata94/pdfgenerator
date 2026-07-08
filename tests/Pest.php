<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(fn () => \Illuminate\Support\Facades\Cache::flush())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Fixtures
|--------------------------------------------------------------------------
|
| Real, minimal, valid files for the conversion pipeline tests — the
| pipeline shells out to real LibreOffice/Ghostscript/ImageMagick binaries,
| so garbage bytes from UploadedFile::fake()->create() won't do.
|
*/

function samplePdfContent(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]/Resources<<>>/Contents 4 0 R>>endobj\n4 0 obj<</Length 44>>stream\nBT /F1 12 Tf 20 100 Td (Hello PDF) Tj ET\nendstream endobj\ntrailer<</Root 1 0 R>>\n";
}

function fakePdfUpload(string $name = 'sample.pdf'): \Illuminate\Http\UploadedFile
{
    return \Illuminate\Http\UploadedFile::fake()->createWithContent($name, samplePdfContent());
}

function encryptedPdfUpload(string $password, string $name = 'encrypted.pdf'): \Illuminate\Http\UploadedFile
{
    $tmpIn = tempnam(sys_get_temp_dir(), 'pdfin').'.pdf';
    $tmpOut = tempnam(sys_get_temp_dir(), 'pdfout').'.pdf';
    file_put_contents($tmpIn, samplePdfContent());

    exec('qpdf --encrypt '.escapeshellarg($password).' '.escapeshellarg($password).' 256 -- '.escapeshellarg($tmpIn).' '.escapeshellarg($tmpOut).' 2>&1');

    $content = file_get_contents($tmpOut);
    @unlink($tmpIn);
    @unlink($tmpOut);

    return \Illuminate\Http\UploadedFile::fake()->createWithContent($name, $content);
}

/**
 * A real, image-only (no text layer) PDF — rendered from a PNG via ImageMagick
 * — for exercising the actual OCR pipeline rather than a fake with no content.
 */
function scannedPdfUpload(string $text = 'HELLO WATERMARK', string $name = 'scanned.pdf'): \Illuminate\Http\UploadedFile
{
    $tmpImage = tempnam(sys_get_temp_dir(), 'scanimg').'.png';
    $tmpPdf = tempnam(sys_get_temp_dir(), 'scanpdf').'.pdf';

    exec('convert -size 400x150 xc:white -gravity center -pointsize 28 -fill black -annotate 0 '.escapeshellarg($text).' '.escapeshellarg($tmpImage).' 2>&1');
    exec('convert '.escapeshellarg($tmpImage).' '.escapeshellarg($tmpPdf).' 2>&1');

    $content = file_get_contents($tmpPdf);
    @unlink($tmpImage);
    @unlink($tmpPdf);

    return \Illuminate\Http\UploadedFile::fake()->createWithContent($name, $content);
}

function samplePngContent(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAAEElEQVR4nGP4z8AARwzEcQCukw/x0F8jngAAAABJRU5ErkJggg==');
}

function fakePngUpload(string $name = 'sample.png'): \Illuminate\Http\UploadedFile
{
    return \Illuminate\Http\UploadedFile::fake()->createWithContent($name, samplePngContent());
}

/**
 * Uploads a file for the given operation and immediately converts it,
 * exercising the real LibreOffice/Ghostscript/ImageMagick binaries — this
 * is the "end-to-end testing of all conversions" checklist item, not a
 * mocked unit test.
 */
function convertOperation(Tests\TestCase $testCase, string $operation, \Illuminate\Http\UploadedFile $file, ?array $options = null): array
{
    $uploadResponse = $testCase->postJson('/api/v1/pdf/upload', array_filter([
        'file' => $file,
        'operation' => $operation,
        'options' => $options,
    ], fn ($value) => $value !== null))->assertOk();

    $jobId = $uploadResponse->json('id');

    $convertResponse = $testCase->postJson('/api/v1/pdf/convert', ['pdfJobId' => $jobId]);

    return ['jobId' => $jobId, 'response' => $convertResponse];
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

