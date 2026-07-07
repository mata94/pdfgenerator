<?php

namespace App\Domain\Pdf\Services;

use App\Application\Commands\Pdf\ConvertPdfCommand;
use App\Domain\Pdf\Enums\PdfJobStatus;
use App\Domain\Pdf\Enums\PdfOperation;
use App\Domain\Pdf\Repositories\Interfaces\PdfJobRepositoryInterface;
use App\Infrastructure\Pdf\Processors\GhostscriptProcessor;
use App\Infrastructure\Pdf\Processors\ImageMagickProcessor;
use App\Infrastructure\Pdf\Processors\LibreOfficeProcessor;
use App\Infrastructure\Pdf\Processors\TabulaProcessor;
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
}
