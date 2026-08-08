<?php

namespace App\Http\Controllers;

use App\Http\Requests\IranCars\StoreIranCarPaymentRequest;
use App\Models\IranCar;
use App\Models\IranCarPayment;
use App\Services\IranCarPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IranCarPaymentController extends Controller
{
    public function __construct(
        private readonly IranCarPaymentService $paymentService
    ) {}

    public function store(StoreIranCarPaymentRequest $request, IranCar $iran_car): RedirectResponse
    {
        Gate::authorize('update', $iran_car);
        $this->paymentService->create($iran_car, $request->validated(), $request->user());

        return redirect()
            ->route('iran-cars.show', $iran_car)
            ->with('success', 'Payment posted to Iran cars receivable.');
    }

    public function destroy(Request $request, IranCar $iran_car, IranCarPayment $iran_car_payment): RedirectResponse
    {
        Gate::authorize('update', $iran_car);
        $this->paymentService->delete($iran_car, $iran_car_payment, $request->user());

        return redirect()
            ->route('iran-cars.show', $iran_car)
            ->with('success', 'Payment reversed and deleted.');
    }
}
