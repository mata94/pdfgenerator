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
            'options' => ['nullable', 'array'],
            'options.angle' => ['required_if:operation,rotate', Rule::in([90, 180, 270])],
            'options.pages' => ['nullable', 'string', 'max:255'],
            'options.password' => ['required_if:operation,protect,unlock', 'string', 'min:1', 'max:255'],
            'options.text' => ['required_if:operation,watermark', 'string', 'max:100'],
            'options.language' => ['nullable', 'string', 'max:32'],
        ];
    }
}
