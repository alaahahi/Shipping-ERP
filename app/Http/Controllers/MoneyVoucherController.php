<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\MoneyVoucherStatus;
use App\Enums\MoneyVoucherType;
use App\Http\Requests\MoneyVouchers\StoreMoneyVoucherRequest;
use App\Http\Requests\MoneyVouchers\UpdateMoneyVoucherRequest;
use App\Models\MoneyVoucher;
use App\Models\Voyage;
use App\Services\CompanyService;
use App\Services\MoneyVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MoneyVoucherController extends Controller
{
    public function __construct(
        private readonly MoneyVoucherService $moneyVoucherService,
        private readonly CompanyService $companyService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MoneyVoucher::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString(),
            'currency' => $request->string('currency')->toString(),
        ];

        $vouchers = $this->moneyVoucherService
            ->paginate($filters)
            ->through(fn (MoneyVoucher $voucher) => $this->moneyVoucherService->transform($voucher));

        return Inertia::render('MoneyVouchers/Index', [
            'vouchers' => $vouchers,
            'filters' => $filters,
            'types' => MoneyVoucherType::options(),
            'statuses' => collect(MoneyVoucherStatus::cases())->map(fn (MoneyVoucherStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->all(),
            'canManage' => $request->user()?->can('accounting.manage') ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', MoneyVoucher::class);

        return Inertia::render('MoneyVouchers/Create', [
            ...$this->formOptions($request),
        ]);
    }

    public function store(StoreMoneyVoucherRequest $request): RedirectResponse
    {
        Gate::authorize('create', MoneyVoucher::class);

        $voucher = $this->moneyVoucherService->create($request->validated(), $request->user());

        return redirect()
            ->route('money-vouchers.show', $voucher)
            ->with('success', 'Money voucher created as draft.');
    }

    public function show(MoneyVoucher $moneyVoucher): Response
    {
        Gate::authorize('view', $moneyVoucher);

        return Inertia::render('MoneyVouchers/Show', [
            'voucher' => $this->moneyVoucherService->transform($moneyVoucher),
            'canManage' => request()->user()?->can('accounting.manage') ?? false,
        ]);
    }

    public function edit(Request $request, MoneyVoucher $moneyVoucher): Response
    {
        Gate::authorize('update', $moneyVoucher);

        return Inertia::render('MoneyVouchers/Edit', [
            'voucher' => $this->moneyVoucherService->transform($moneyVoucher),
            ...$this->formOptions($request),
        ]);
    }

    public function update(UpdateMoneyVoucherRequest $request, MoneyVoucher $moneyVoucher): RedirectResponse
    {
        Gate::authorize('update', $moneyVoucher);

        $this->moneyVoucherService->update($moneyVoucher, $request->validated());

        return redirect()
            ->route('money-vouchers.show', $moneyVoucher)
            ->with('success', 'Money voucher updated.');
    }

    public function destroy(MoneyVoucher $moneyVoucher): RedirectResponse
    {
        Gate::authorize('delete', $moneyVoucher);

        $this->moneyVoucherService->delete($moneyVoucher);

        return redirect()
            ->route('money-vouchers.index')
            ->with('success', 'Money voucher deleted.');
    }

    public function post(Request $request, MoneyVoucher $moneyVoucher): RedirectResponse
    {
        Gate::authorize('post', $moneyVoucher);

        $this->moneyVoucherService->post($moneyVoucher, $request->user());

        return redirect()
            ->route('money-vouchers.show', $moneyVoucher)
            ->with('success', 'Money voucher posted to accounting.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $service = $this->moneyVoucherService;

        return [
            'types' => MoneyVoucherType::options(),
            'currencies' => collect(Currency::cases())->map(fn (Currency $currency) => [
                'value' => $currency->value,
                'label' => $currency->value,
            ])->all(),
            'paymentAccounts' => [
                Currency::USD->value => $service->paymentAccountOptions(Currency::USD->value),
                Currency::AED->value => $service->paymentAccountOptions(Currency::AED->value),
            ],
            'voyages' => Voyage::query()
                ->latest('sailing_date')
                ->limit(100)
                ->get(['id', 'voyage_number'])
                ->map(fn (Voyage $voyage) => [
                    'id' => $voyage->id,
                    'label' => $voyage->voyage_number,
                ])
                ->all(),
            'companies' => $this->companyService->options(),
            'defaults' => [
                'type' => $request->string('type')->toString() ?: MoneyVoucherType::Receipt->value,
                'company_id' => $request->integer('company_id') ?: null,
                'voyage_id' => $request->integer('voyage_id') ?: null,
                'amount' => $request->input('amount'),
                'currency' => $request->string('currency')->toString() ?: Currency::USD->value,
                'counterparty' => $request->string('counterparty')->toString() ?: '',
            ],
        ];
    }
}