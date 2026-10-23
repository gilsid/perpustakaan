<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Query;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $q = Query::string($request, 'q');

        $logs = ActivityLog::query()
            ->with('user')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w
                    ->where('message', 'ilike', $like)
                    ->orWhere('action', 'ilike', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $like)));
            })
            ->latest('created_at')->latest('id')
            ->paginate(25, pageName: 'halaman')->withQueryString();

        return view('admin.logs', ['nav' => 'logs', 'logs' => $logs, 'q' => $q]);
    }
}
