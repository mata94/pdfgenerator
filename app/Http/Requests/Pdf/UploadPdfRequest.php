<?php

namespace App\Http\Requests\Pdf;

use App\Domain\Pdf\Enums\PdfOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png',
            ],
            'operation' => ['required', 'string', Rule::enum(PdfOperation::class)],
        ];
    }
}
