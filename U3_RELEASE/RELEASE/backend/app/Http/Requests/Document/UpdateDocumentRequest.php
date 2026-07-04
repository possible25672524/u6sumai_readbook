<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');
        return $this->user()->can('update', $document);
    }

    public function rules(): array
    {
        return [
            'title'          => ['sometimes', 'required', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:2000'],
            'visibility'     => ['nullable', 'string', Rule::in([
                Document::VISIBILITY_PRIVATE,
                Document::VISIBILITY_SHARED,
                Document::VISIBILITY_PUBLIC,
            ])],
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],

            // Allow manual correction of extracted text (OCR fix flow)
            'extracted_text' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'กรุณาระบุชื่อเอกสาร',
        ];
    }
}
