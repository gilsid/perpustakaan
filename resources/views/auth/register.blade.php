<x-layouts.auth title="Daftar">
<div class="auth-card">
    <h1>Buat akun</h1>
    <p class="lead">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
    <form method="post" action="{{ route('register.store') }}" novalidate>
        @csrf
        <x-field name="name" label="Nama lengkap" :required="true" autocomplete="name" maxlength="120" autofocus />
        <x-field name="email" label="Email" type="email" :required="true" autocomplete="email" />
        <x-field name="password" label="Kata sandi" type="password" :required="true" autocomplete="new-password" :hint="'Minimal '.config('library.auth.min_password').' karakter.'" />
        <x-field name="password_confirmation" label="Ulangi kata sandi" type="password" :required="true" autocomplete="new-password" />
        <button class="btn btn--primary btn--lg btn--block" type="submit">Buat akun</button>
    </form>
</div>
</x-layouts.auth>
