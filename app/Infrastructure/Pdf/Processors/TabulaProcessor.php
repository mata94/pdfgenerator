<?php

namespace App\Infrastructure\Pdf\Processors;

class TabulaProcessor
{
    private string $jarPath;

    public function __construct()
    {
        $this->jarPath = env('TABULA_JAR', '/opt/tabula/tabula.jar');
    }

    /**
     * Extract every table in the PDF to a single CSV file.
     *
     * LibreOffice has no PDF-import filter into Calc, so PDF->Excel is done as
     * PDF -> CSV (here) -> XLSX (LibreOfficeProcessor). Tabula's extraction is
     * heuristic, so the result quality depends on the source PDF's structure.
     */
    public function pdfToCsv(string $inputPath, string $outputCsvPath): string
    {
        $command = sprintf(
            'java -jar %s -f CSV -p all %s -o %s 2>&1',
            escapeshellarg($this->jarPath),
            escapeshellarg($inputPath),
            escapeshellarg($outputCsvPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Tabula extraction failed: '.implode("\n", $output));
        }

        // Tabula writes an empty file when it finds no tables; LibreOffice
        // needs at least one cell to produce a valid workbook, so drop in a
        // placeholder rather than failing the whole conversion.
        if (trim((string) @file_get_contents($outputCsvPath)) === '') {
            file_put_contents($outputCsvPath, "No tables were detected in this PDF\n");
        }

        return $outputCsvPath;
    }
}
