<x-layouts.admin title="Kartu anggota" nav="users">
<header class="admin-head"><h1>Kartu anggota</h1></header>
@if ($errors->any())
    <div class="flashes"><div class="alert alert--error" role="alert"><x-icon name="alert" /><p>{{ $errors->first() }}</p></div></div>
@endif
<form class="card" method="post" action="{{ route('admin.users.membership.update', $member) }}">
    @csrf @method('PATCH')
    <p class="muted">{{ $member->name }} · {{ $member->email }}</p>
    <x-field name="member_number" label="Nomor anggota" :value="$member->member_number" :required="true" maxlength="30" hint="Dipindai petugas saat check-out." />
    <x-field name="membership_expires_at" label="Keanggotaan berakhir" type="date" :value="$member->membership_expires_at?->toDateString()" hint="Kosongkan bila tidak ada batas waktu." />
    <div class="form-actions">
        <a class="btn btn--ghost" href="{{ route('admin.users.index') }}">Batal</a>
        <button class="btn btn--primary" type="submit">Simpan</button>
    </div>
</form>
</x-layouts.admin>
