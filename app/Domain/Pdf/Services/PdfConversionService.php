<?php

namespace App\Domain\Pdf\Services;

use App\Application\Commands\Pdf\ConvertPdfCommand;
use App\Domain\Pdf\Enums\PdfJobStatus;
use App\Domain\Pdf\Enums\PdfOperation;
use App\Domain\Pdf\Repositories\Interfaces\PdfJobRepositoryInterface;
use App\Infrastructure\Pdf\Processors\GhostscriptProcessor;
use App\Infrastructure\Pdf\Processors\ImageMagickProcessor;
use App\Infrastructure\Pdf\Processors\LibreOfficeProcessor;
use App\Infrastructure\Pdf\Processors\OcrProcessor;
use App\Infrastructure\Pdf\Processors\QpdfProcessor;
use App\Infrastructure\Pdf\Processors\TabulaProcessor;
use App\Infrastructure\Pdf\Processors\WatermarkProcessor;
use App\Models\PdfJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfConversionService
{
    public function __construct(
        private PdfJobRepositoryInterface $repository,
        private LibreOfficeProcessor $libreOfficeProcessor,
        private GhostscriptProcessor $ghostscriptProcessor,
        private ImageMagickProcessor $imageMagickProcessor,
        private TabulaProcessor $tabulaProcessor,
        private QpdfProcessor $qpdfProcessor,
        private WatermarkProcessor $watermarkProcessor,
        private OcrProcessor $ocrProcessor,
    ) {
    }

    public function convert(ConvertPdfCommand $command): PdfJob
    {
        $job = $this->repository->find($command->getPdfJobId());

        if (! $job) {
            throw new \RuntimeException('PDF job not found.');
        }

        $disk = Storage::disk('local');
        $root = $disk->path('');
        $inputPath = $disk->path($job->input_file);
        $outputDir = dirname($inputPath);
        $outputBasename = $outputDir.'/'.pathinfo($inputPath, PATHINFO_FILENAME);

        try {
            $absoluteOutputPath = match (PdfOperation::from($job->operation)) {
                PdfOperation::PDF_TO_WORD => $this->libreOfficeProcessor->convert($inputPath, $outputDir, 'docx', 'writer_pdf_import'),
                PdfOperation::PDF_TO_PPTX => $this->libreOfficeProcessor->convert($inputPath, $outputDir, 'pptx', 'impress_pdf_import'),
                PdfOperation::PDF_TO_EXCEL => $this->pdfToExcel($inputPath, $outputDir, $outputBasename),
                PdfOperation::WORD_TO_PDF, PdfOperation::PPTX_TO_PDF, PdfOperation::EXCEL_TO_PDF => $this->libreOfficeProcessor->convert($inputPath, $outputDir, 'pdf'),
                PdfOperation::PDF_TO_JPG => $this->imageMagickProcessor->pdfToImage($inputPath, $outputBasename, 'jpg'),
                PdfOperation::PDF_TO_PNG => $this->imageMagickProcessor->pdfToImage($inputPath, $outputBasename, 'png'),
                PdfOperation::JPG_TO_PDF, PdfOperation::PNG_TO_PDF => $this->imageMagickProcessor->imageToPdf($inputPath, $outputBasename),
                PdfOperation::COMPRESS => $this->ghostscriptProcessor->compress($inputPath, $outputBasename.'-compressed.pdf'),
                PdfOperation::ROTATE => $this->qpdfProcessor->rotate(
                    $inputPath,
                    $outputBasename.'-rotated.pdf',
                    (int) ($job->options['angle'] ?? 90),
                    $job->options['pages'] ?? null,
                ),
                PdfOperation::PROTECT => $this->qpdfProcessor->encrypt(
                    $inputPath,
                    $outputBasename.'-protected.pdf',
                    (string) ($job->options['password'] ?? ''),
                ),
                PdfOperation::UNLOCK => $this->qpdfProcessor->decrypt(
                    $inputPath,
                    $outputBasename.'-unlocked.pdf',
                    (string) ($job->options['password'] ?? ''),
                ),
                PdfOperation::WATERMARK => $this->watermark(
                    $inputPath,
                    $outputBasename.'-watermarked.pdf',
                    (string) ($job->options['text'] ?? ''),
                ),
                PdfOperation::OCR => $this->ocrProcessor->ocr(
                    $inputPath,
                    $outputBasename.'-ocr.pdf',
                    $job->options['language'] ?? 'eng',
                ),
            };
        } catch (\RuntimeException $e) {
            $this->repository->update($job, ['status' => PdfJobStatus::FAILED->value]);
            throw $e;
        }

        return $this->repository->update($job, [
            'output_file' => Str::after($absoluteOutputPath, $root),
            'status' => PdfJobStatus::COMPLETED->value,
        ]);
    }

    /**
     * PDF -> Excel: no LibreOffice PDF-import filter exists for Calc, so
     * extract tables to CSV with tabula, then let LibreOffice turn the CSV
     * into a real .xlsx (CSV:44,34,UTF8 = comma-separated, "-quoted, UTF-8).
     */
    private function pdfToExcel(string $inputPath, string $outputDir, string $outputBasename): string
    {
        $csvPath = $outputBasename.'.csv';

        $this->tabulaProcessor->pdfToCsv($inputPath, $csvPath);

        try {
            return $this->libreOfficeProcessor->convert($csvPath, $outputDir, 'xlsx', 'CSV:44,34,UTF8');
        } finally {
            @unlink($csvPath);
        }
    }

    private function watermark(string $inputPath, string $outputPath, string $text): string
    {
        $stampPath = $outputPath.'.stamp.pdf';

        $this->watermarkProcessor->createStamp($inputPath, $stampPath, $text);

        try {
            return $this->qpdfProcessor->overlay($inputPath, $stampPath, $outputPath);
        } finally {
            @unlink($stampPath);
        }
    }
}
