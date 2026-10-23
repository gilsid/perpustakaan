{{-- Tombol simpan/hapus favorit. Args: $book, $isFavorite, $class, $wrap (opsional) --}}
<form @isset($wrap) class="{{ $wrap }}" @endisset method="post" action="{{ route($isFavorite ? 'favorites.destroy' : 'favorites.store', $book) }}">
    @csrf @method($isFavorite ? 'DELETE' : 'PUT')
    <button class="{{ $class }}" type="submit" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"><x-icon name="bookmark" :class="$isFavorite ? 'is-filled' : ''" />{{ $isFavorite ? 'Tersimpan di favorit' : 'Simpan ke favorit' }}</button>
</form>
