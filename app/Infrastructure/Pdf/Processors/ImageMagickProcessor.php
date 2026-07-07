<?php

namespace App\Infrastructure\Pdf\Processors;

class ImageMagickProcessor
{
    public function pdfToImage(string $inputPath, string $outputPath, string $format = 'jpg'): string
    {
        $command = sprintf(
            'convert -density 150 %s -quality 90 %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath.'.'.$format)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('ImageMagick conversion failed: '.implode("\n", $output));
        }

        return $outputPath.'.'.$format;
    }

    public function imageToPdf(string $inputPath, string $outputPath): string
    {
        $command = sprintf(
            'convert %s %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath.'.pdf')
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('ImageMagick conversion failed: '.implode("\n", $output));
        }

        return $outputPath.'.pdf';
    }
}
