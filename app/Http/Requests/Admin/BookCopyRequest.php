<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookCopyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $copyId = $this->route('copy')?->id;

        return [
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'code' => ['required', 'string', 'max:40', Rule::unique('book_copies', 'code')->ignore($copyId)],
            'location' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::enum(BookCopyStatus::class)],
            'replacement_cost' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }
}
