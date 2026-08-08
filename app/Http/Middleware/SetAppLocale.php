<?php

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use App\Enums\SettingKey;
use App\Services\SettingService;
use App\Support\ApplicationTimezone;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    public function __construct(
        private readonly SettingService $settingService
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->settingService->get(SettingKey::AppLocale, AppLocale::Arabic->value);

        if (! in_array($locale, AppLocale::values(), true)) {
            $locale = AppLocale::Arabic->value;
        }

        App::setLocale($locale);

        ApplicationTimezone::apply($this->settingService->get(SettingKey::AppTimezone));

        return $next($request);
    }
}
