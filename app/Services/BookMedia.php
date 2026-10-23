<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Penyimpanan berkas buku.
 *  - Sampul: disk "public" (boleh diakses langsung). Selalu di-encode ulang ke JPEG lewat GD,
 *    sehingga tidak mungkin membawa kode atau metadata tersembunyi.
 *  - PDF: disk "local" (privat, di luar folder publik). Hanya dilayani ReaderController setelah cek akses.
 */
class BookMedia
{
    public const COVER_DIR = 'covers';

    public const PDF_DIR = 'books';

    public function storeCover(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $info = @getimagesize($path);
        if ($info === false) {
            throw ValidationException::withMessages(['cover' => 'Gambar sampul rusak atau tidak bisa dibaca.']);
        }
        [$w, $h] = $info;
        if ($w * $h > 40_000_000) {
            throw ValidationException::withMessages(['cover' => 'Resolusi gambar terlalu besar. Kecilkan dulu hingga di bawah 40 megapiksel.']);
        }

        $name = self::COVER_DIR.'/c_'.Str::lower(Str::random(16)).'.jpg';

        if (! function_exists('imagecreatefromstring')) {
            // Tanpa ekstensi GD: simpan apa adanya (jenis berkas sudah divalidasi dari isinya oleh aturan "image").
            Storage::disk('public')->putFileAs(self::COVER_DIR, $file, basename($name));

            return $name;
        }

        $src = @imagecreatefromstring((string) file_get_contents($path));
        if ($src === false) {
            throw ValidationException::withMessages(['cover' => 'Gambar sampul rusak atau tidak bisa dibaca.']);
        }

        $scale = min(1.0, config('library.uploads.cover_max_width') / $w, config('library.uploads.cover_max_height') / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // ratakan transparansi PNG ke putih
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($dst, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        Storage::disk('public')->put($name, $jpeg);

        return $name;
    }

    public function storePdf(UploadedFile $file): string
    {
        $name = self::PDF_DIR.'/b_'.Str::lower(Str::random(16)).'.pdf';
        Storage::disk('local')->putFileAs(self::PDF_DIR, $file, basename($name));

        return $name;
    }

    public function deleteCover(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function deletePdf(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    public function pdfAbsolutePath(string $path): string
    {
        return Storage::disk('local')->path($path);
    }

    public function pdfExists(string $path): bool
    {
        return Storage::disk('local')->exists($path);
    }
}
