<?php

namespace App\Http\Requests\Admin;

use App\Rules\PdfSignature;
use Illuminate\Foundation\Http\FormRequest;

class BookRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'call_number' => ['nullable', 'string', 'max:40'],
            'isbn' => ['nullable', 'string', 'max:20', 'regex:/^[0-9Xx\- ]{10,20}$/'],
            'publisher' => ['nullable', 'string', 'max:160'],
            'published_year' => ['nullable', 'integer', 'between:1000,2200'],
            'pages' => ['nullable', 'integer', 'between:1,100000'],
            'language' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('library.uploads.cover_max_kb')],
            'pdf' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:'.config('library.uploads.pdf_max_kb'), new PdfSignature],
            'remove_cover' => ['nullable', 'boolean'],
            'remove_pdf' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Judul', 'author' => 'Penulis', 'category_id' => 'Kategori', 'call_number' => 'Nomor panggil',
            'isbn' => 'ISBN', 'publisher' => 'Penerbit', 'published_year' => 'Tahun terbit', 'pages' => 'Jumlah halaman',
            'language' => 'Bahasa', 'description' => 'Sinopsis', 'cover' => 'Sampul', 'pdf' => 'PDF',
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.regex' => 'ISBN hanya boleh berisi angka, spasi, dan tanda hubung.',
            'cover.uploaded' => 'Sampul gagal diunggah. Ukurannya melebihi batas upload_max_filesize di php.ini ('.ini_get('upload_max_filesize').').',
            'pdf.uploaded' => 'PDF gagal diunggah. Ukurannya melebihi batas upload_max_filesize di php.ini ('.ini_get('upload_max_filesize').'). Naikkan batas itu di php.ini.',
            'pdf.mimetypes' => 'Berkas harus berupa PDF yang valid.',
            'cover.image' => 'Sampul harus berupa gambar JPG, PNG, atau WebP.',
            'cover.mimes' => 'Sampul harus berupa gambar JPG, PNG, atau WebP.',
        ];
    }
}
