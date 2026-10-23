<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Di luar produksi: lazy loading (N+1) dan akses atribut yang tidak ada langsung melempar galat.
        Model::shouldBeStrict(! $this->app->isProduction());

        Carbon::setLocale(config('app.locale'));
        Paginator::defaultView('vendor.pagination.library');

        // /admin/buku/baru dan /admin/buku/{buku}/ubah (bukan create/edit).
        Route::resourceVerbs(['create' => 'baru', 'edit' => 'ubah']);

        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
        Gate::define('member', fn (User $user): bool => $user->isMember());
    }
}
