<?php

namespace App\Infrastructure\Pdf\Processors;

class OcrProcessor
{
    public function ocr(string $inputPath, string $outputPath, string $language = 'eng'): string
    {
        // --skip-text: pages that already carry a text layer are left as-is
        // instead of erroring, so re-running OCR on an already-searchable PDF is a no-op.
        $command = sprintf(
            'ocrmypdf --skip-text -l %s %s %s 2>&1',
            escapeshellarg($language),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('ocrmypdf failed: '.implode("\n", $output));
        }

        return $outputPath;
    }
}
