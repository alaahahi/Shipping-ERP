<?php

namespace App\Http\Controllers;

use App\Enums\AppLocale;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\CountryService;
use App\Services\SettingService;
use App\Services\SystemAdminService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly CountryService $countryService,
        private readonly UserService $userService,
        private readonly SystemAdminService $systemAdminService
    ) {}

    public function edit(Request $request): Response
    {
        Gate::authorize('viewAny', Setting::class);

        $tab = $request->string('tab')->toString() ?: 'company';
        $user = $request->user();

        $payload = [
            'tab' => $tab,
            'settings' => $this->settingService->formValues(),
            'options' => [
                'locales' => collect(AppLocale::available())->map(fn (AppLocale $locale) => [
                    'value' => $locale->value,
                    'label' => $locale->label(),
                    'rtl' => $locale->isRtl(),
                ]),
                'currencies' => [
                    ['value' => 'USD', 'label' => 'USD - US Dollar'],
                    ['value' => 'AED', 'label' => 'AED - UAE Dirham'],
                    ['value' => 'IQD', 'label' => 'IQD - Iraqi Dinar'],
                    ['value' => 'EUR', 'label' => 'EUR - Euro'],
                ],
                'timezones' => [
                    ['value' => 'Asia/Baghdad', 'label' => 'Asia/Baghdad'],
                    ['value' => 'Asia/Dubai', 'label' => 'Asia/Dubai'],
                    ['value' => 'Asia/Tehran', 'label' => 'Asia/Tehran'],
                    ['value' => 'UTC', 'label' => 'UTC'],
                ],
            ],
            'canManage' => $user?->can(Permission::SettingsManage->value) ?? false,
            'canManageUsers' => $user?->can(Permission::UsersManage->value) ?? false,
            'canViewUsers' => $user?->can(Permission::UsersView->value) ?? false,
            'countries' => $this->countryService->transformMany($this->countryService->all()),
            'users' => null,
            'userFilters' => ['search' => '', 'role' => ''],
            'roles' => [],
            'logs' => null,
            'logLevel' => $request->string('level')->toString(),
        ];

        if ($payload['canViewUsers']) {
            $userFilters = [
                'search' => $request->string('search')->toString(),
                'role' => $request->string('role')->toString(),
            ];

            $payload['userFilters'] = $userFilters;
            $payload['roles'] = $this->roleOptions();
            $payload['users'] = $this->userService
                ->paginate($userFilters)
                ->through(fn (User $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'email' => $item->email,
                    'role' => $item->roles->first()?->name,
                    'created_at' => $item->created_at?->toDateString(),
                ]);
        }

        if ($payload['canManage']) {
            try {
                $payload['logs'] = $this->systemAdminService->tailLog(
                    200,
                    $request->string('level')->toString() ?: null
                );
            } catch (Throwable $e) {
                $payload['logs'] = [
                    'file' => 'laravel.log',
                    'lines' => [['text' => $e->getMessage(), 'level' => 'WARNING']],
                    'total' => 1,
                ];
            }
        }

        return Inertia::render('Settings/Edit', $payload);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $validated = $request->validated();

        $this->settingService->updateMany([
            'company.name' => $validated['company']['name'],
            'company.email' => $validated['company']['email'] ?? '',
            'company.phone' => $validated['company']['phone'] ?? '',
            'company.address' => $validated['company']['address'] ?? '',
            'app.timezone' => $validated['app']['timezone'],
            'app.locale' => $validated['app']['locale'],
            'app.currency' => $validated['app']['currency'],
            'whatsapp.tenant_id' => $validated['whatsapp']['tenant_id'] ?? '',
            'whatsapp.enabled' => ($validated['whatsapp']['enabled'] ?? false) ? '1' : '0',
        ]);

        return redirect()
            ->route('settings.edit', ['tab' => 'company'])
            ->with('success', 'Settings updated successfully.');
    }

    public function migrate(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        try {
            $output = $this->systemAdminService->runMigrate($request->user());
        } catch (Throwable $e) {
            return redirect()
                ->route('settings.edit', ['tab' => 'system'])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('settings.edit', ['tab' => 'system'])
            ->with('success', 'Migrations finished.')
            ->with('migrate_output', $output);
    }

    public function downloadLogs(): StreamedResponse
    {
        Gate::authorize('manage', Setting::class);

        return $this->systemAdminService->downloadLog();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return Role::query()
            ->whereIn('name', array_map(
                static fn (SystemRole $role): string => $role->value,
                SystemRole::cases()
            ))
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (Role $role) => [
                'value' => $role->name,
                'label' => SystemRole::tryFrom($role->name)?->label() ?? ucfirst($role->name),
            ])
            ->values()
            ->all();
    }
}
