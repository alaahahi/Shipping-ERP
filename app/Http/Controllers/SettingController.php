<?php

namespace App\Http\Controllers;

use App\Enums\AppLocale;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService
    ) {}

    public function edit(): Response
    {
        Gate::authorize('viewAny', Setting::class);

        return Inertia::render('Settings/Edit', [
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
            'canManage' => request()->user()?->can('settings.manage') ?? false,
        ]);
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
            ->route('settings.edit')
            ->with('success', 'Settings updated successfully.');
    }
}
