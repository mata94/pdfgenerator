<?php

namespace App\Domain\Pdf\Enums;

enum PdfJobStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';
}
