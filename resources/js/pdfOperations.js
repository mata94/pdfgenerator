export const FILE_TYPE_STYLES = {
    word: { bg: '#E6F1FB', color: '#185FA5' },
    excel: { bg: '#EAF3DE', color: '#3B6D11' },
    pptx: { bg: '#FAEEDA', color: '#854F0B' },
    photo: { bg: '#FCEBEB', color: '#E24B4A' },
    pdf: { bg: '#FCEBEB', color: '#E24B4A' },
};

export const PDF_OPERATIONS = [
    {
        value: 'pdf_to_word',
        label: 'PDF to Word',
        description: 'PDF to editable DOCX',
        accept: '.pdf',
        fileType: 'word',
    },
    {
        value: 'pdf_to_pptx',
        label: 'PDF to PowerPoint',
        description: 'PDF to editable PPTX',
        accept: '.pdf',
        fileType: 'pptx',
    },
    {
        value: 'pdf_to_excel',
        label: 'PDF to Excel',
        description: 'PDF to editable XLSX',
        accept: '.pdf',
        fileType: 'excel',
    },
    {
        value: 'pdf_to_jpg',
        label: 'PDF to JPG',
        description: 'PDF pages to JPG images',
        accept: '.pdf',
        fileType: 'photo',
    },
    {
        value: 'pdf_to_png',
        label: 'PDF to PNG',
        description: 'PDF pages to PNG images',
        accept: '.pdf',
        fileType: 'photo',
    },
    {
        value: 'word_to_pdf',
        label: 'Word to PDF',
        description: 'DOCX to PDF',
        accept: '.doc,.docx',
        fileType: 'word',
    },
    {
        value: 'pptx_to_pdf',
        label: 'PowerPoint to PDF',
        description: 'PPTX to PDF',
        accept: '.ppt,.pptx',
        fileType: 'pptx',
    },
    {
        value: 'excel_to_pdf',
        label: 'Excel to PDF',
        description: 'XLSX to PDF',
        accept: '.xls,.xlsx',
        fileType: 'excel',
    },
    {
        value: 'jpg_to_pdf',
        label: 'JPG to PDF',
        description: 'JPG image to PDF',
        accept: '.jpg,.jpeg',
        fileType: 'photo',
    },
    {
        value: 'png_to_pdf',
        label: 'PNG to PDF',
        description: 'PNG image to PDF',
        accept: '.png',
        fileType: 'photo',
    },
    {
        value: 'compress',
        label: 'Compress PDF',
        description: 'Reduce PDF file size',
        accept: '.pdf',
        fileType: 'pdf',
    },
    {
        value: 'rotate',
        label: 'Rotate PDF',
        description: 'Rotate all pages',
        accept: '.pdf',
        fileType: 'pdf',
        optionsSchema: [
            {
                key: 'angle',
                type: 'select',
                label: 'Rotation angle',
                options: [
                    { value: 90, label: '90°' },
                    { value: 180, label: '180°' },
                    { value: 270, label: '270°' },
                ],
                required: true,
            },
        ],
    },
    {
        value: 'protect',
        label: 'Protect PDF',
        description: 'Add a password',
        accept: '.pdf',
        fileType: 'pdf',
        optionsSchema: [
            {
                key: 'password',
                type: 'password',
                label: 'Password',
                required: true,
            },
        ],
    },
    {
        value: 'unlock',
        label: 'Unlock PDF',
        description: 'Remove a password',
        accept: '.pdf',
        fileType: 'pdf',
        optionsSchema: [
            {
                key: 'password',
                type: 'password',
                label: 'Current password',
                required: true,
            },
        ],
    },
    {
        value: 'watermark',
        label: 'Watermark PDF',
        description: 'Add a text watermark',
        accept: '.pdf',
        fileType: 'pdf',
        optionsSchema: [
            {
                key: 'text',
                type: 'text',
                label: 'Watermark text',
                placeholder: 'CONFIDENTIAL',
                required: true,
            },
        ],
    },
    {
        value: 'ocr',
        label: 'OCR PDF',
        description: 'Make a scanned PDF searchable',
        accept: '.pdf',
        fileType: 'pdf',
    },
];

const EXTENSION_TO_OPERATION = {
    pdf: 'compress',
    doc: 'word_to_pdf',
    docx: 'word_to_pdf',
    ppt: 'pptx_to_pdf',
    pptx: 'pptx_to_pdf',
    xls: 'excel_to_pdf',
    xlsx: 'excel_to_pdf',
    jpg: 'jpg_to_pdf',
    jpeg: 'jpg_to_pdf',
    png: 'png_to_pdf',
};

export function operationForFile(file) {
    const ext = file.name.split('.').pop()?.toLowerCase();

    return EXTENSION_TO_OPERATION[ext] ?? null;
}

export function findOperation(value) {
    return PDF_OPERATIONS.find((operation) => operation.value === value) ?? null;
}
