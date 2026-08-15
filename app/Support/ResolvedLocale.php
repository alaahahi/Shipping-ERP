<?php

namespace App\Support;

use App\Enums\AppLocale;
use App\Enums\SettingKey;
use App\Services\SettingService;
use Illuminate\Http\Request;

class ResolvedLocale
{
    public static function fromRequest(Request $request): string
    {
        $candidates = [
            $request->input('locale'),
            $request->cookie('erp_locale'),
            app(SettingService::class)->get(SettingKey::AppLocale, AppLocale::Arabic->value),
        ];

        foreach ($candidates as $candidate) {
            if (in_array((string) $candidate, AppLocale::values(), true)) {
                return (string) $candidate;
            }
        }

        return AppLocale::Arabic->value;
    }
}
