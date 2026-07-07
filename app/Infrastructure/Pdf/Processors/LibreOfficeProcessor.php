<?php

namespace App\Infrastructure\Pdf\Processors;

use Illuminate\Support\Str;

class LibreOfficeProcessor
{
    public function convert(string $inputPath, string $outputDir, string $format, ?string $infilter = null): string
    {
        // LibreOffice needs a writable profile directory ($HOME isn't
        // writable for the php-fpm user) and a dedicated one per run avoids
        // profile-lock conflicts between concurrent conversions.
        $profileDir = sys_get_temp_dir().'/libreoffice-'.Str::uuid()->toString();

        // Point HOME/XDG_CACHE_HOME at the same writable dir so GTK/dconf stop
        // emitting "unable to create directory '/var/www/.cache/dconf'" warnings
        // (which otherwise pollute the captured output and the 'Error:' check).
        $command = sprintf(
            'HOME=%1$s XDG_CACHE_HOME=%1$s libreoffice --headless -env:UserInstallation=file://%1$s%2$s --convert-to %3$s %4$s --outdir %5$s 2>&1',
            escapeshellarg($profileDir),
            $infilter ? ' --infilter='.escapeshellarg($infilter) : '',
            escapeshellarg($format),
            escapeshellarg($inputPath),
            escapeshellarg($outputDir)
        );

        exec($command, $output, $returnCode);

        exec('rm -rf '.escapeshellarg($profileDir));

        $outputText = implode("\n", $output);

        // LibreOffice's headless CLI exits 0 even when it can't find a
        // matching export filter (e.g. no PDF import path exists for Calc),
        // so a clean exit code alone doesn't mean the conversion succeeded.
        if ($returnCode !== 0 || str_contains($outputText, 'Error:')) {
            throw new \RuntimeException('LibreOffice conversion failed: '.$outputText);
        }

        $filename = pathinfo($inputPath, PATHINFO_FILENAME).'.'.$format;

        return $outputDir.'/'.$filename;
    }
}
