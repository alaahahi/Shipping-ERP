<?php

namespace App\Http\Controllers;

use App\Http\Requests\LandTrips\AssignPaymentChassisRequest;
use App\Http\Requests\LandTrips\DestroyCompanyWalletEntryRequest;
use App\Http\Requests\LandTrips\StoreCompanyWalletEntryRequest;
use App\Http\Requests\LandTrips\UpdateCompanyWalletAttachmentRequest;
use App\Models\Company;
use App\Models\CompanyWalletEntry;
use App\Models\LandTrip;
use App\Models\User;
use App\Services\CompanyWalletService;
use App\Services\LandPaymentChassisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyWalletController extends Controller
{
    public function __construct(
        private readonly CompanyWalletService $walletService,
        private readonly LandPaymentChassisService $chassisService
    ) {}

    public function store(StoreCompanyWalletEntryRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->walletService->create(
            $company,
            $request->safe()->except('attachment'),
            $request->user(),
            $request->file('attachment')
        );

        return back()->with('success', 'Wallet entry saved.');
    }

    public function destroy(DestroyCompanyWalletEntryRequest $request, Company $company, CompanyWalletEntry $entry): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->walletService->delete($company, $entry, $request->user());

        return back()->with('success', 'Wallet entry deleted.');
    }

    public function assignChassis(
        AssignPaymentChassisRequest $request,
        Company $company,
        CompanyWalletEntry $entry
    ): RedirectResponse {
        Gate::authorize('update', $entry);

        $result = $this->chassisService->assign(
            $company,
            $entry,
            $request->user(),
            $request->validated('car_ids') ?? [],
            $this->chassisService->normalizeLines((string) $request->input('chassis_text', ''))
        );

        $count = count($result['assigned']);
        $skipped = count($result['skipped']);
        $message = "Assigned {$count} chassis.";
        if ($skipped > 0) {
            $message .= " {$skipped} lines skipped.";
        }

        return back()->with('success', $message);
    }

    public function print(Company $company, CompanyWalletEntry $entry): Response
    {
        Gate::authorize('viewAny', LandTrip::class);

        $payload = $this->walletService->printPayload($company, $entry);

        return Inertia::render('LandTrips/WalletPrint', $payload);
    }

    public function updateAttachment(
        UpdateCompanyWalletAttachmentRequest $request,
        Company $company,
        CompanyWalletEntry $entry
    ): RedirectResponse {
        Gate::authorize('update', $entry);

        $user = $request->user();
        $file = $request->file('attachment');
        abort_unless($user instanceof User && $file instanceof UploadedFile, 422);

        $this->walletService->replaceAttachment($company, $entry, $user, $file);

        return back()->with('success', 'Attachment updated.');
    }

    public function showAttachment(Company $company, CompanyWalletEntry $entry): StreamedResponse
    {
        Gate::authorize('viewAny', LandTrip::class);

        if ((int) $entry->company_id !== (int) $company->id) {
            abort(404);
        }

        if (! $entry->attachment_path || ! Storage::disk('public')->exists($entry->attachment_path)) {
            abort(404);
        }

        $downloadName = $entry->attachment_original_name ?: basename($entry->attachment_path);

        return Storage::disk('public')->response(
            $entry->attachment_path,
            $downloadName,
            [
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $downloadName).'"',
            ]
        );
    }
}
