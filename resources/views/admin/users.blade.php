<x-layouts.admin title="Pengguna" nav="users">
<header class="admin-head"><h1>Pengguna <span class="count">{{ number_format($users->total(), 0, ',', '.') }}</span></h1></header>
<form class="filter-row" method="get" action="{{ route('admin.users.index') }}" role="search">
    <label class="sr-only" for="uq">Cari pengguna</label>
    <input id="uq" type="search" name="q" value="{{ $q }}" placeholder="Cari nama atau email">
    <label class="sr-only" for="urole">Peran</label>
    <select id="urole" name="peran">
        <option value="">Semua peran</option>
        @foreach (\App\Enums\UserRole::cases() as $r)<option value="{{ $r->value }}" @selected($role === $r)>{{ $r->label() }}</option>@endforeach
    </select>
    <button class="btn btn--secondary" type="submit">Cari</button>
</form>
@if ($users->isEmpty())
    <div class="empty"><h2>Tidak ada pengguna</h2><p>Tidak ada pengguna yang cocok dengan pencarian ini.</p></div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Pengguna</th><th scope="col">Peran</th><th scope="col">Status</th><th scope="col">Pinjaman</th><th scope="col">Terakhir masuk</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                @php($self = $u->is(auth()->user()))
                <tr>
                    <td><strong>{{ $u->name }}</strong>@if ($self) <span class="muted small">(Anda)</span>@endif<br><span class="muted small">{{ $u->email }}</span></td>
                    <td><span class="badge {{ $u->isAdmin() ? 'badge--info' : 'badge--muted' }}">{{ $u->role->label() }}</span></td>
                    <td><span class="badge {{ $u->is_active ? 'badge--ok' : 'badge--danger' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span>@if ($u->must_change_password) <span class="badge badge--warn">Sandi sementara</span>@endif</td>
                    <td>{{ number_format($u->active_loans_count, 0, ',', '.') }}</td>
                    <td>{{ $u->last_login_at ? $u->last_login_at->translatedFormat('j M Y, H.i') : 'Belum pernah' }}</td>
                    <td class="actions">
                        @unless ($self)
                            <form method="post" action="{{ route('admin.users.reset-password', $u) }}" data-confirm="Buat kata sandi sementara untuk {{ $u->name }}? Kata sandi lamanya langsung tidak berlaku." data-confirm-label="Reset kata sandi">
                                @csrf<button class="btn btn--ghost btn--sm" type="submit"><x-icon name="lock" />Reset sandi</button>
                            </form>
                            <form method="post" action="{{ route('admin.users.role', $u) }}" data-confirm="{{ $u->isAdmin() ? 'Cabut akses admin dari ' : 'Jadikan admin: ' }}{{ $u->name }}?" data-confirm-label="Ya, ubah">
                                @csrf @method('PATCH')<input type="hidden" name="role" value="{{ $u->isAdmin() ? 'member' : 'admin' }}">
                                <button class="btn btn--ghost btn--sm" type="submit">{{ $u->isAdmin() ? 'Jadikan anggota' : 'Jadikan admin' }}</button>
                            </form>
                            <form method="post" action="{{ route('admin.users.status', $u) }}" @if ($u->is_active) data-confirm="Nonaktifkan akun {{ $u->name }}? Ia tidak bisa masuk lagi sampai diaktifkan." data-confirm-label="Nonaktifkan" @endif>
                                @csrf @method('PATCH')<button class="btn {{ $u->is_active ? 'btn--danger-ghost' : 'btn--secondary' }} btn--sm" type="submit">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
@endif
</x-layouts.admin>
