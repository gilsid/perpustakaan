<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Loan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $tz = config('app.timezone');

        return view('admin.dashboard', [
            'nav' => 'dashboard',
            'titles' => Book::count(),
            'digital' => Book::whereNotNull('pdf_path')->count(),
            'members' => User::where('role', UserRole::Member)->count(),
            'reviewCount' => Review::count(),
            'activeLoans' => Loan::active()->count(),
            'expiring' => Loan::active()->where('due_at', '<', now()->addDays(3))->count(),
            // Peminjaman per hari selama 14 hari terakhir (zona waktu aplikasi).
            'perDay' => collect(DB::select(
                'SELECT d::date AS day, count(l.id) AS n
                 FROM generate_series((now() AT TIME ZONE ?)::date - 13, (now() AT TIME ZONE ?)::date, interval \'1 day\') d
                 LEFT JOIN loans l ON (l.borrowed_at AT TIME ZONE ?)::date = d::date
                 GROUP BY d ORDER BY d',
                [$tz, $tz, $tz],
            )),
            'topBooks' => Book::query()->has('loans')->withCount('loans')->orderByDesc('loans_count')->orderBy('title')->limit(5)->get(),
            'recent' => ActivityLog::query()->with('user')->latest('created_at')->latest('id')->limit(8)->get(),
            'latestLoans' => Loan::active()->with(['user', 'book'])->latest('borrowed_at')->limit(6)->get(),
        ]);
    }
}
