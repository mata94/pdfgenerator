<?php

namespace App\Domain\Pdf\Enums;

enum PdfOperation: string
{
    case PDF_TO_WORD  = 'pdf_to_word';
    case PDF_TO_PPTX  = 'pdf_to_pptx';
    case PDF_TO_EXCEL = 'pdf_to_excel';
    case PDF_TO_JPG   = 'pdf_to_jpg';
    case PDF_TO_PNG   = 'pdf_to_png';

    case WORD_TO_PDF  = 'word_to_pdf';
    case PPTX_TO_PDF  = 'pptx_to_pdf';
    case EXCEL_TO_PDF = 'excel_to_pdf';
    case JPG_TO_PDF   = 'jpg_to_pdf';
    case PNG_TO_PDF   = 'png_to_pdf';

    case COMPRESS     = 'compress';

    case ROTATE       = 'rotate';
    case PROTECT      = 'protect';
    case UNLOCK       = 'unlock';
    case WATERMARK    = 'watermark';
    case OCR          = 'ocr';
}
