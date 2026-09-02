<?php

namespace App\Providers;

use App\Services\PermissionService;
use App\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Invite;
use App\Models\Document;
use App\Models\Newsletter\Subscriber;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use App\Policies\InvitePolicy;
use App\Policies\DocumentPolicy;
use App\Policies\SubscriberPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionService::class, function ($app) {
            return new PermissionService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Always generate HTTPS URLs when the app runs in production or the
        // request itself arrived over HTTPS (directly or via a trusted proxy).
        // This prevents mixed-content errors on generated URLs and redirects.
        $request = $this->app->bound('request') ? $this->app['request'] : null;

        if (config('app.env') === 'production' || $request?->isSecure()) {
            // Normalize an http:// APP_URL to https so the forced root can never
            // leak insecure URLs into redirects (e.g. logout -> Location header).
            URL::forceRootUrl(preg_replace('#^http://#i', 'https://', (string) config('app.url')));
            URL::forceScheme('https');

            // Force HTTPS for all routes
            $request?->server->set('HTTPS', 'on');
        }

        Vite::prefetch(concurrency: 3);

        // Register policies
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Invite::class, InvitePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Subscriber::class, SubscriberPolicy::class);

        // Delegate string-based abilities to PermissionService (with team context)
        Gate::before(function ($user, string $ability) {
            if (!is_string($ability)) {
                return null; // not our concern
            }

            // Super admin bypass
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            // Only handle abilities that correspond to a known permission
            $exists = PermissionModel::query()->where('name', $ability)->where('guard_name', 'web')->exists();
            if (!$exists) {
                return null; // Let other gates/policies handle
            }

            /** @var PermissionRegistrar $registrar */
            $registrar = app(PermissionRegistrar::class);
            $teamId = method_exists($registrar, 'getPermissionsTeamId') ? $registrar->getPermissionsTeamId() : null;

            /** @var PermissionService $svc */
            $svc = app(PermissionService::class);
            return $svc->can($user, $ability, $teamId);
        });
    }
}
