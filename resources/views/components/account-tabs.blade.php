@props(['tab'])
<nav class="tabs" aria-label="Rak saya">
    <a href="{{ route('account.loans') }}" @if ($tab === 'loans') aria-current="page" @endif><x-icon name="book" />Pinjaman</a>
    <a href="{{ route('account.favorites') }}" @if ($tab === 'favorites') aria-current="page" @endif><x-icon name="bookmark" />Favorit</a>
    <a href="{{ route('account.profile') }}" @if ($tab === 'profile') aria-current="page" @endif><x-icon name="user" />Profil</a>
</nav>
