<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserPreferenceRequest;
use Illuminate\Http\RedirectResponse;

class UserPreferenceController extends Controller
{
    public function update(UpdateUserPreferenceRequest $request): RedirectResponse
    {
        return back()->cookie('erp_locale', $request->validated('locale'), 60 * 24 * 365);
    }
}
