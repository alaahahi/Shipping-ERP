<?php

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use App\Enums\SettingKey;
use App\Services\NotificationDispatchService;
use App\Services\SettingService;
use App\Support\ResolvedLocale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $settings = app(SettingService::class);
        $notifications = app(NotificationDispatchService::class);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ] : null,
            ],
            'appSettings' => [
                'companyName' => $settings->get(SettingKey::CompanyName),
                'companyPhone' => $settings->get(SettingKey::CompanyPhone),
                'companyAddress' => $settings->get(SettingKey::CompanyAddress),
                'companyLogoUrl' => $settings->logoUrl(),
                'currency' => $settings->get(SettingKey::AppCurrency),
                'locale' => ResolvedLocale::fromRequest($request),
                'defaultLocale' => $settings->get(SettingKey::AppLocale),
                'timezone' => $settings->get(SettingKey::AppTimezone),
                'locales' => collect(AppLocale::available())->map(fn (AppLocale $locale) => [
                    'value' => $locale->value,
                    'label' => $locale->label(),
                    'rtl' => $locale->isRtl(),
                ])->values()->all(),
            ],
            'notifications' => $user ? [
                'unread_count' => $notifications->unreadCount($user),
                'recent' => $notifications->recentFor($user),
            ] : [
                'unread_count' => 0,
                'recent' => [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'migrate_output' => fn () => $request->session()->get('migrate_output'),
            ],
        ];
    }
}
