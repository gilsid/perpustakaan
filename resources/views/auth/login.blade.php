<x-layouts.auth title="Masuk">
<div class="auth-card">
    <h1>Masuk</h1>
    <p class="lead">Belum punya akun? <a href="{{ route('register') }}">Daftar gratis</a></p>
    @error('form')<div class="alert alert--error" role="alert"><x-icon name="alert" /><p>{{ $message }}</p></div>@enderror
    <form method="post" action="{{ route('login.store') }}" novalidate>
        @csrf
        <x-field name="email" label="Email" type="email" :required="true" autocomplete="username" autofocus />
        <x-field name="password" label="Kata sandi" type="password" :required="true" autocomplete="current-password" />
        <button class="btn btn--primary btn--lg btn--block" type="submit">Masuk</button>
    </form>
</div>
</x-layouts.auth>
