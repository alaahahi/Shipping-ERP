<?php

namespace App\Http\Controllers;

use App\Enums\IranCarSaleState;
use App\Enums\Permission;
use App\Http\Requests\IranCars\StoreIranCarPoolPaymentRequest;
use App\Models\IranCar;
use App\Models\IranCarPoolPayment;
use App\Services\IranCarPoolPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IranCarPoolPaymentController extends Controller
{
    public function __construct(
        private readonly IranCarPoolPaymentService $poolPaymentService
    ) {}

    public function store(StoreIranCarPoolPaymentRequest $request): RedirectResponse
    {
        Gate::authorize('create', IranCar::class);
        $this->poolPaymentService->create($request->validated(), $request->user());

        return redirect()
            ->route('iran-cars.index', ['sale_state' => IranCarSaleState::Sold->value])
            ->with('success', 'Pool payment posted to Iran cars receivable.');
    }

    public function destroy(Request $request, IranCarPoolPayment $iran_car_pool_payment): RedirectResponse
    {
        Gate::authorize('create', IranCar::class);
        abort_unless($request->user()?->can(Permission::IranCarsManage->value) ?? false, 403);

        $this->poolPaymentService->delete($iran_car_pool_payment, $request->user());

        return redirect()
            ->route('iran-cars.index', ['sale_state' => IranCarSaleState::Sold->value])
            ->with('success', 'Pool payment reversed and deleted.');
    }
}
