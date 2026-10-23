<x-layouts.app title="Profil saya">
<div class="container page">
    <header class="page__head"><h1>{{ $user->isAdmin() ? 'Profil admin' : 'Rak saya' }}</h1></header>
    @if ($user->isMember())<x-account-tabs :tab="$tab" />@endif

    <div class="two-col">
        <section class="card" aria-labelledby="akun">
            <h2 id="akun">Data akun</h2>
            <form method="post" action="{{ route('account.profile.update') }}" novalidate>
                @csrf @method('PUT')
                <x-field name="name" label="Nama lengkap" :value="$user->name" :required="true" autocomplete="name" maxlength="120" />
                <x-field name="email" label="Email" type="email" :value="$user->email" :required="true" autocomplete="email" />
                <x-field name="email_password" label="Kata sandi saat ini" type="password" autocomplete="current-password" hint="Hanya diperlukan bila Anda mengganti email." />
                <button class="btn btn--primary" type="submit">Simpan perubahan</button>
            </form>
            <p class="muted small">Bergabung sejak {{ $user->created_at->translatedFormat('j M Y') }}</p>
        </section>

        <section class="card" id="sandi" aria-labelledby="sandi-judul">
            <h2 id="sandi-judul">Ganti kata sandi</h2>
            @if ($user->must_change_password)
                <div class="alert alert--warning"><x-icon name="alert" /><p>Anda memakai kata sandi sementara dari admin. Buat kata sandi baru untuk melanjutkan.</p></div>
            @endif
            <form method="post" action="{{ route('account.password.update') }}" novalidate>
                @csrf @method('PUT')
                <x-field name="current_password" bag="password" label="Kata sandi saat ini" type="password" :required="true" autocomplete="current-password" />
                <x-field name="password" bag="password" label="Kata sandi baru" type="password" :required="true" autocomplete="new-password" :hint="'Minimal '.config('library.auth.min_password').' karakter.'" />
                <x-field name="password_confirmation" bag="password" label="Ulangi kata sandi baru" type="password" :required="true" autocomplete="new-password" />
                <button class="btn btn--primary" type="submit">Ganti kata sandi</button>
            </form>
        </section>
    </div>
</div>
</x-layouts.app>
