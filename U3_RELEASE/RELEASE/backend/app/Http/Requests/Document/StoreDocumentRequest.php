<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source_type' => ['required', 'string', Rule::in([
                Document::SOURCE_PDF,
                Document::SOURCE_DOCX,
                Document::SOURCE_TXT,
                Document::SOURCE_IMAGE,
                Document::SOURCE_AUDIO,
                Document::SOURCE_VIDEO,
                Document::SOURCE_YOUTUBE,
                Document::SOURCE_GOOGLE_DRIVE,
            ])],

            // File upload (required for file-based types)
            'file' => [
                Rule::requiredIf(fn() => in_array($this->input('source_type'), [
                    Document::SOURCE_PDF,
                    Document::SOURCE_DOCX,
                    Document::SOURCE_TXT,
                    Document::SOURCE_IMAGE,
                    Document::SOURCE_AUDIO,
                    Document::SOURCE_VIDEO,
                ])),
                'nullable',
                'file',
                'max:204800', // 200 MB max
                'mimes:pdf,docx,txt,png,jpg,jpeg,gif,webp,mp3,mp4,wav,m4a,webm,mpeg',
            ],

            // URL (required for URL-based types)
            'source_url' => [
                Rule::requiredIf(fn() => in_array($this->input('source_type'), [
                    Document::SOURCE_YOUTUBE,
                    Document::SOURCE_GOOGLE_DRIVE,
                ])),
                'nullable',
                'url',
                'max:2048',
            ],

            'category_ids'  => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],

            'visibility' => ['nullable', 'string', Rule::in([
                Document::VISIBILITY_PRIVATE,
                Document::VISIBILITY_SHARED,
                Document::VISIBILITY_PUBLIC,
            ])],

            'language' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'       => 'กรุณาระบุชื่อเอกสาร',
            'source_type.required' => 'กรุณาระบุประเภทแหล่งข้อมูล',
            'source_type.in'       => 'ประเภทแหล่งข้อมูลไม่ถูกต้อง',
            'file.required'        => 'กรุณาแนบไฟล์',
            'file.max'             => 'ขนาดไฟล์ต้องไม่เกิน 200 MB',
            'file.mimes'           => 'รองรับไฟล์ประเภท PDF, DOCX, TXT, PNG, JPG, MP3, MP4, WAV, M4A, WEBM เท่านั้น',
            'source_url.required'  => 'กรุณาระบุ URL',
            'source_url.url'       => 'URL ไม่ถูกต้อง',
        ];
    }
}
