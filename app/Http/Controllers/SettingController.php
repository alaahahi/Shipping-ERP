<?php

namespace App\Http\Controllers;

use App\Enums\AppLocale;
use App\Enums\LandTripCarRowTone;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\LandTripCarStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\CountryService;
use App\Services\LandTripCashAccountService;
use App\Services\LandTripCarStatusService;
use App\Services\SettingService;
use App\Services\SystemAdminService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        private readonly LandTripCarStatusService $landTripCarStatusService,
        private readonly LandTripCashAccountService $landTripCashAccountService,
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
            'cashAccountOptions' => $this->landTripCashAccountService->options(),
            'countries' => $this->countryService->transformMany($this->countryService->all()),
            'landCarStatuses' => $this->landTripCarStatusService->transformMany(
                LandTripCarStatus::query()->with('country')->orderBy('sort_order')->orderBy('id')->get()
            ),
            'rowTones' => collect(LandTripCarRowTone::cases())->map(fn ($tone) => [
                'value' => $tone->value,
                'label' => $tone->label(),
            ])->all(),
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
            'land_trips.cash_account_id' => (string) ($validated['land_trips']['cash_account_id'] ?? ''),
        ]);

        if (! empty($validated['company']['remove_logo']) || ! empty($validated['remove_logo'])) {
            $this->settingService->clearLogo();
        } elseif ($request->file('logo') || $request->file('company.logo')) {
            $this->settingService->storeLogo(
                $request->file('logo') ?? $request->file('company.logo')
            );
        }

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

    public function clearLogs(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        try {
            $this->systemAdminService->clearLogs($request->user());
        } catch (Throwable $e) {
            return redirect()
                ->route('settings.edit', ['tab' => 'system'])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('settings.edit', ['tab' => 'system'])
            ->with('success', 'Application logs cleared.');
    }

    public function databaseInsights(): JsonResponse
    {
        try {
            $driver = (string) DB::getDriverName();
            $path = (string) config('database.connections.'.$driver.'.database', '');
            $dbSize = ($driver === 'sqlite' && File::exists($path)) ? (int) File::size($path) : null;

            $pageSize = $driver === 'sqlite' ? (int) DB::selectOne('PRAGMA page_size')->page_size : 0;
            $pageCount = $driver === 'sqlite' ? (int) DB::selectOne('PRAGMA page_count')->page_count : 0;
            $freelistCount = $driver === 'sqlite' ? (int) DB::selectOne('PRAGMA freelist_count')->freelist_count : 0;
            $usedBytes = $pageSize > 0 ? ($pageCount - $freelistCount) * $pageSize : null;
            $freeBytes = $pageSize > 0 ? $freelistCount * $pageSize : null;

            $sizeMap = [];
            if ($driver === 'sqlite') {
                try {
                    foreach (DB::select('SELECT name, SUM(pgsize) AS sz FROM dbstat GROUP BY name') as $r) {
                        $sizeMap[(string) $r->name] = (int) $r->sz;
                    }
                } catch (Throwable) {
                }
            }

            $tables = $driver === 'sqlite'
                ? DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
                : DB::select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');

            $items = [];
            foreach ($tables as $t) {
                $name = (string) ($t->name ?? '');
                $rows = 0;
                try {
                    $rows = (int) DB::table($name)->count();
                } catch (Throwable) {
                }
                $sz = $sizeMap[$name] ?? null;
                $items[] = ['name' => $name, 'rows' => $rows, 'size_bytes' => $sz, 'percent' => $dbSize && $sz ? round($sz / $dbSize * 100, 2) : null];
            }
            usort($items, fn ($a, $b) => ($b['size_bytes'] ?? -1) <=> ($a['size_bytes'] ?? -1));

            return response()->json(['driver' => $driver, 'db_size' => $dbSize, 'used_bytes' => $usedBytes, 'free_bytes' => $freeBytes, 'tables' => $items]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function vacuumDatabase(): JsonResponse
    {
        try {
            $driver = (string) DB::getDriverName();
            if ($driver !== 'sqlite') {
                return response()->json(['message' => "VACUUM غير مدعوم لـ {$driver}.", 'skipped' => true]);
            }
            $path = (string) config('database.connections.sqlite.database');
            $before = File::exists($path) ? (int) File::size($path) : null;
            DB::statement('VACUUM');
            $after = File::exists($path) ? (int) File::size($path) : null;
            $saved = ($before !== null && $after !== null) ? max(0, $before - $after) : null;

            return response()->json(['message' => 'تم تنفيذ VACUUM بنجاح.', 'before' => $before, 'after' => $after, 'saved' => $saved]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
