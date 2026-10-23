<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Query;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = Query::string($request, 'q');
        $role = Query::enum($request, 'peran', UserRole::class);

        $users = User::query()
            ->withCount(['loans as active_loans_count' => fn ($l) => $l->active()])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like));
            })
            ->when($role, fn ($query, UserRole $r) => $query->where('role', $r))
            ->latest()->orderByDesc('id')
            ->paginate(config('library.admin_per_page'), pageName: 'halaman')->withQueryString();

        return view('admin.users', ['nav' => 'users', 'users' => $users, 'q' => $q, 'role' => $role]);
    }
}
