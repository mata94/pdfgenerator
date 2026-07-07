<?php

namespace App\Infrastructure\Pdf\Processors;

class GhostscriptProcessor
{
    public function compress(string $inputPath, string $outputPath, string $quality = 'ebook'): string
    {
        $command = sprintf(
            'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/%s -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
            $quality,
            escapeshellarg($outputPath),
            escapeshellarg($inputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Ghostscript compression failed: '.implode("\n", $output));
        }

        return $outputPath;
    }
}
