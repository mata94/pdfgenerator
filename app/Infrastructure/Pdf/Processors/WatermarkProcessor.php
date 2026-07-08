<?php

namespace App\Infrastructure\Pdf\Processors;

class WatermarkProcessor
{
    private const DEFAULT_WIDTH = 612.0;

    private const DEFAULT_HEIGHT = 792.0;

    /**
     * Builds a one-page PDF containing $text as a diagonal gray stamp, sized to
     * match $inputPath's first page so QpdfProcessor::overlay() lines it up.
     */
    public function createStamp(string $inputPath, string $stampPath, string $text): string
    {
        [$width, $height] = $this->detectPageSize($inputPath);

        file_put_contents($stampPath, $this->buildStampPdf($width, $height, $text));

        return $stampPath;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function detectPageSize(string $inputPath): array
    {
        $qdf = shell_exec(sprintf(
            'qpdf --qdf --object-streams=disable %s - 2>/dev/null',
            escapeshellarg($inputPath)
        )) ?? '';

        if (preg_match('/\/MediaBox\s*\[\s*([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s*\]/s', $qdf, $matches)) {
            $width = (float) $matches[3] - (float) $matches[1];
            $height = (float) $matches[4] - (float) $matches[2];

            if ($width > 0 && $height > 0) {
                return [$width, $height];
            }
        }

        return [self::DEFAULT_WIDTH, self::DEFAULT_HEIGHT];
    }

    /**
     * Hand-builds a minimal, valid single-page PDF (own xref table, standard
     * Helvetica font — no embedding needed) with $text drawn diagonally in
     * light gray across the page, roughly centered.
     */
    private function buildStampPdf(float $width, float $height, string $text): string
    {
        $fontSize = max(14.0, min(72.0, min($width, $height) / 8));
        $escapedText = $this->escapePdfString($text);

        $angle = deg2rad(45);
        $cos = cos($angle);
        $sin = sin($angle);

        // Helvetica isn't monospace; 0.55x font size per char is a reasonable
        // average for approximate centering — a watermark doesn't need to be pixel-exact.
        $estimatedTextWidth = strlen($text) * $fontSize * 0.55;
        $dx = -$estimatedTextWidth / 2;
        $dy = -$fontSize * 0.35;

        $originX = $width / 2 + $dx * $cos - $dy * $sin;
        $originY = $height / 2 + $dx * $sin + $dy * $cos;

        $content = sprintf(
            "q\n0.6 g\nBT\n/F1 %.2F Tf\n%.4F %.4F %.4F %.4F %.2F %.2F Tm\n(%s) Tj\nET\nQ",
            $fontSize,
            $cos,
            $sin,
            -$sin,
            $cos,
            $originX,
            $originY,
            $escapedText
        );

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
                $width,
                $height
            ),
            4 => sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content),
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        return $this->assemblePdf($objects);
    }

    private function escapePdfString(string $text): string
    {
        // Keep to printable ASCII (base-14 fonts have no reliable wider encoding
        // here) and escape the two characters PDF literal strings care about.
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';

        return addcslashes($text, '\\()');
    }

    /**
     * @param  array<int, string>  $objects
     */
    private function assemblePdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";

        return $pdf;
    }
}
