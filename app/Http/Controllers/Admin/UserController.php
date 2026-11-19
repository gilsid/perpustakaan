<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Query;
use App\Support\SearchTerm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = SearchTerm::fromRequest($request);
        $role = Query::enum($request, 'peran', UserRole::class);

        $users = User::query()
            ->withCount(['loans as active_loans_count' => fn ($l) => $l->active()])
            ->when(! $search->isBlank(), fn ($query) => $query->whereAny(['name', 'email'], 'ilike', $search->like()))
            ->when($role, fn ($query, UserRole $r) => $query->where('role', $r))
            ->latest()->orderByDesc('id')
            ->paginate(config('library.admin_per_page'), pageName: 'halaman')->withQueryString();

        return view('admin.users', ['nav' => 'users', 'users' => $users, 'q' => $search->value, 'role' => $role]);
    }
}
