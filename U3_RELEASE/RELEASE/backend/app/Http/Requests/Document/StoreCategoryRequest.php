<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        return in_array($user->role?->slug, ['admin', 'teacher']);
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name'        => ['required', 'string', 'max:100'],
            'slug'        => [
                'nullable',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id'   => [
                'nullable',
                'integer',
                'exists:categories,id',
                // Prevent circular references
                Rule::notIn($categoryId ? [$categoryId] : []),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'กรุณาระบุชื่อหมวดหมู่',
            'slug.unique'     => 'Slug นี้ถูกใช้งานแล้ว',
            'parent_id.exists' => 'หมวดหมู่หลักไม่พบในระบบ',
        ];
    }
}
