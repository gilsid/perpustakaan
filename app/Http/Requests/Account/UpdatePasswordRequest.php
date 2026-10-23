<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:'.config('library.auth.min_password'), 'max:72', 'confirmed', 'different:current_password'],
        ];
    }

    /** Galat disimpan di bag terpisah agar tidak tercampur dengan form data akun di halaman yang sama. */
    protected $errorBag = 'password';
}
