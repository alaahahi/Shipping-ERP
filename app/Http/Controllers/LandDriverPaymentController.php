<?php

namespace App\Http\Controllers;

use App\Http\Requests\LandTrips\AssignPaymentChassisRequest;
use App\Http\Requests\LandTrips\DestroyLandDriverPaymentRequest;
use App\Http\Requests\LandTrips\StoreLandDriverPaymentRequest;
use App\Http\Requests\LandTrips\UpdateLandDriverPaymentAttachmentRequest;
use App\Models\Company;
use App\Models\LandDriverPayment;
use App\Models\User;
use App\Services\LandDriverPaymentService;
use App\Services\LandPaymentChassisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandDriverPaymentController extends Controller
{
    public function __construct(
        private readonly LandDriverPaymentService $driverPaymentService,
        private readonly LandPaymentChassisService $chassisService
    ) {}

    public function store(StoreLandDriverPaymentRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandDriverPayment::class);

        $this->driverPaymentService->create(
            $company,
            $request->safe()->except('attachment'),
            $request->user(),
            $request->file('attachment')
        );

        return back()->with('success', 'Driver payment posted.');
    }

    public function destroy(
        DestroyLandDriverPaymentRequest $request,
        Company $company,
        LandDriverPayment $payment
    ): RedirectResponse {
        Gate::authorize('delete', $payment);

        $this->driverPaymentService->delete($company, $payment, $request->user());

        return back()->with('success', 'Driver payment deleted.');
    }

    public function assignChassis(
        AssignPaymentChassisRequest $request,
        Company $company,
        LandDriverPayment $payment
    ): RedirectResponse {
        Gate::authorize('update', $payment);

        $result = $this->chassisService->assign(
            $company,
            $payment,
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

    public function updateAttachment(
        UpdateLandDriverPaymentAttachmentRequest $request,
        Company $company,
        LandDriverPayment $payment
    ): RedirectResponse {
        Gate::authorize('update', $payment);

        $user = $request->user();
        $file = $request->file('attachment');
        abort_unless($user instanceof User && $file instanceof UploadedFile, 422);

        $this->driverPaymentService->replaceAttachment($company, $payment, $user, $file);

        return back()->with('success', 'Attachment updated.');
    }

    public function showAttachment(Company $company, LandDriverPayment $payment): StreamedResponse
    {
        Gate::authorize('view', $payment);

        if ((int) $payment->company_id !== (int) $company->id) {
            abort(404);
        }

        if (! $payment->attachment_path || ! Storage::disk('public')->exists($payment->attachment_path)) {
            abort(404);
        }

        $downloadName = $payment->attachment_original_name ?: basename($payment->attachment_path);

        return Storage::disk('public')->response(
            $payment->attachment_path,
            $downloadName,
            [
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $downloadName).'"',
            ]
        );
    }
}
