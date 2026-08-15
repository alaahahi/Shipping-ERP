<?php

namespace App\Http\Middleware;

use App\Enums\SettingKey;
use App\Services\SettingService;
use App\Support\ApplicationTimezone;
use App\Support\ResolvedLocale;
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
        $locale = ResolvedLocale::fromRequest($request);

        App::setLocale($locale);

        ApplicationTimezone::apply($this->settingService->get(SettingKey::AppTimezone));

        return $next($request);
    }
}
