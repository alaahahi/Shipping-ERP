<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Policies\RolePolicy;
use App\Support\ApplicationTimezone;
use App\Support\ViteBuildDirectory;
use App\Models\ShipExpense;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureViteForSharedHosting();

        ApplicationTimezone::apply();

        Relation::morphMap([
            'ship_expense' => ShipExpense::class,
        ]);

        Vite::prefetch(concurrency: 3);

        Gate::policy(Role::class, RolePolicy::class);

        Gate::define('viewReports', fn ($user) => $user->can(Permission::ReportsView->value));

        Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }

            return null;
        });
    }

    /**
     * cPanel often uses the project root as the document root.
     * Read public/build first, then the mirrored ./build at the web root.
     * Asset URLs stay /build/... so Apache can serve either copy.
     */
    private function configureViteForSharedHosting(): void
    {
        $directory = ViteBuildDirectory::relativeToPublic(
            public_path('build/manifest.json'),
            base_path('build/manifest.json'),
        );

        Vite::useBuildDirectory($directory);

        if (ViteBuildDirectory::usesWebRootMirror($directory)) {
            Vite::createAssetPathsUsing(
                fn (string $path, ?bool $secure = null) => asset(ViteBuildDirectory::toPublicUrlPath($path), $secure)
            );
        }
    }
}
