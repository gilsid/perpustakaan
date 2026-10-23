<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('categories', 'name')->ignore($this->route('category')),
            ],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Nama kategori'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Nama kategori itu sudah ada.'];
    }
}
