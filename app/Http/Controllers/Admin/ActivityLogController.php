<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\SearchTerm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = SearchTerm::fromRequest($request);

        $logs = ActivityLog::query()
            ->with('user')
            ->when(! $search->isBlank(), function ($query) use ($search) {
                $like = $search->like();
                $query->where(fn ($w) => $w
                    ->whereAny(['message', 'action'], 'ilike', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $like)));
            })
            ->latest('created_at')->latest('id')
            ->paginate(25, pageName: 'halaman')->withQueryString();

        return view('admin.logs', ['nav' => 'logs', 'logs' => $logs, 'q' => $search->value]);
    }
}
