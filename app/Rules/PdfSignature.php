<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/** Memastikan isi berkas benar-benar PDF (bukan hanya ekstensi/MIME dari klien). */
class PdfSignature implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || $value->getRealPath() === false) {
            return;
        }
        $head = (string) file_get_contents($value->getRealPath(), false, null, 0, 5);
        if ($head !== '%PDF-') {
            $fail('Berkas harus berupa PDF yang valid.');
        }
    }
}
