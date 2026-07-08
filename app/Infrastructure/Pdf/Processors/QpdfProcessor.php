<?php

namespace App\Infrastructure\Pdf\Processors;

class QpdfProcessor
{
    /**
     * qpdf exit code 3 means warnings only — the output file is still valid.
     */
    private const EXIT_WARNINGS = 3;

    public function rotate(string $inputPath, string $outputPath, int $angle, ?string $pages = null): string
    {
        $range = $pages ?: '1-z';

        $command = sprintf(
            'qpdf %s %s %s 2>&1',
            escapeshellarg(sprintf('--rotate=+%d:%s', $angle, $range)),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 && $returnCode !== self::EXIT_WARNINGS) {
            throw new \RuntimeException('qpdf rotate failed: '.implode("\n", $output));
        }

        return $outputPath;
    }

    public function encrypt(string $inputPath, string $outputPath, string $password): string
    {
        // Same string used as both user and owner password; 256-bit AES.
        $command = sprintf(
            'qpdf --encrypt %s %s 256 -- %s %s 2>&1',
            escapeshellarg($password),
            escapeshellarg($password),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 && $returnCode !== self::EXIT_WARNINGS) {
            throw new \RuntimeException('qpdf encrypt failed: '.implode("\n", $output));
        }

        return $outputPath;
    }

    public function decrypt(string $inputPath, string $outputPath, string $password): string
    {
        $command = sprintf(
            'qpdf %s --decrypt %s %s 2>&1',
            escapeshellarg('--password='.$password),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        // qpdf exits 2 for a wrong/missing password (or a file that isn't encrypted as expected).
        if ($returnCode === 2) {
            throw new \RuntimeException('Wrong password — could not unlock the PDF.');
        }

        if ($returnCode !== 0 && $returnCode !== self::EXIT_WARNINGS) {
            throw new \RuntimeException('qpdf decrypt failed: '.implode("\n", $output));
        }

        return $outputPath;
    }

    /**
     * Stamps every page of $inputPath with the single-page $stampPath, repeating
     * it once the (1-page) stamp source is exhausted so it covers every page.
     */
    public function overlay(string $inputPath, string $stampPath, string $outputPath): string
    {
        $command = sprintf(
            'qpdf --overlay %s --repeat=1-z -- %s %s 2>&1',
            escapeshellarg($stampPath),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 && $returnCode !== self::EXIT_WARNINGS) {
            throw new \RuntimeException('qpdf overlay failed: '.implode("\n", $output));
        }

        return $outputPath;
    }
}
