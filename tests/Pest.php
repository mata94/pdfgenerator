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
function convertOperation(Tests\TestCase $testCase, string $operation, \Illuminate\Http\UploadedFile $file): array
{
    $uploadResponse = $testCase->postJson('/api/v1/pdf/upload', [
        'file' => $file,
        'operation' => $operation,
    ])->assertOk();

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

