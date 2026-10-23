<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'email_password' => ['nullable', 'string'],
        ];
    }

    /** Mengganti email mengubah identitas login, jadi wajib konfirmasi kata sandi. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $changed = strcasecmp((string) $this->input('email'), $this->user()->email) !== 0;
                if ($changed && ! $validator->errors()->has('email')
                    && ! Hash::check((string) $this->input('email_password'), $this->user()->password)) {
                    $validator->errors()->add('email_password', 'Kata sandi saat ini salah.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['email_password' => 'Kata sandi saat ini'];
    }
}
