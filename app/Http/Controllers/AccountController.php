<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Http\Requests\Accounts\AccountLedgerRequest;
use App\Http\Requests\Accounts\StoreAccountMovementRequest;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountMovementRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountLedgerExportService;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AccountLedgerExportService $accountLedgerExportService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Account::class);

        $filters = $this->chartFilters($request);

        return Inertia::render('Accounts/Index', [
            'accounts' => $this->chartPage($filters, 1),
            'filters' => $filters,
            'types' => collect(AccountType::cases())->map(fn (AccountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'currencies' => collect(Currency::cases())->map(fn (Currency $currency) => [
                'value' => $currency->value,
                'label' => $currency->label(),
            ]),
            'canManage' => $request->user()?->can('accounting.manage') ?? false,
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Account::class);

        $filters = $this->chartFilters($request);

        return response()->json($this->chartPage(
            $filters,
            max(1, $request->integer('page', 1)),
        ));
    }

    public function create(): Response
    {
        Gate::authorize('create', Account::class);

        return Inertia::render('Accounts/Create', $this->formOptions());
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        Gate::authorize('create', Account::class);

        $this->accountService->create($request->validated());

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function show(AccountLedgerRequest $request, Account $account): Response
    {
        Gate::authorize('view', $account);

        $filters = $this->ledgerFilters($request);

        $ledger = $this->accountService->ledger($account, $filters);

        return Inertia::render('Accounts/Show', [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'type_label' => $account->type->label(),
                'currency' => $account->currency->value,
                'is_system' => $account->is_system,
                'is_active' => $account->is_active,
                'show_on_dashboard' => $account->show_on_dashboard,
                'description' => $account->description,
                'balance' => $this->accountService->balance($account),
                'has_posted_movements' => $this->accountService->hasPostedMovements($account),
            ],
            'filters' => $filters,
            'period_debit' => $ledger['period_debit'],
            'period_credit' => $ledger['period_credit'],
            'period_net' => $ledger['period_net'],
            'lines' => $ledger['lines'],
            'counterpartAccounts' => $this->accountService->counterpartOptions($account),
            'canManage' => $request->user()?->can('accounting.manage') ?? false,
        ]);
    }

    public function exportExcel(AccountLedgerRequest $request, Account $account): StreamedResponse
    {
        Gate::authorize('view', $account);

        return $this->accountLedgerExportService->excel($account, $this->ledgerFilters($request));
    }

    public function exportPdf(AccountLedgerRequest $request, Account $account): HttpResponse
    {
        Gate::authorize('view', $account);

        return $this->accountLedgerExportService->pdf($account, $this->ledgerFilters($request));
    }

    public function storeMovement(StoreAccountMovementRequest $request, Account $account): RedirectResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $this->accountService->postMovement(
            $account,
            $request->validated(),
            $request->user(),
            $request->file('attachment')
        );

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Movement posted.');
    }

    public function updateMovement(UpdateAccountMovementRequest $request, Account $account, JournalEntry $journal): RedirectResponse
    {
        Gate::authorize('updateMeta', $journal);
        $this->accountService->assertTouchesAccount($account, $journal);
        $this->accountService->updateMovementMeta(
            $journal,
            $request->safe()->except('attachment'),
            $request->file('attachment')
        );

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Movement updated.');
    }

    public function voidMovement(Request $request, Account $account, JournalEntry $journal): RedirectResponse
    {
        Gate::authorize('void', $journal);
        $this->accountService->assertTouchesAccount($account, $journal);

        $request->validate([
            'void_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->accountService->voidMovement(
            $journal,
            $request->user(),
            $request->string('void_reason')->toString() ?: 'Deleted from account ledger'
        );

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Movement voided.');
    }

    public function reverseMovement(Request $request, Account $account, JournalEntry $journal): RedirectResponse
    {
        Gate::authorize('reverse', $journal);
        $this->accountService->assertTouchesAccount($account, $journal);

        $this->accountService->reverseMovement($journal, $request->user());

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Movement reversed.');
    }

    public function edit(Account $account): Response
    {
        Gate::authorize('update', $account);

        return Inertia::render('Accounts/Edit', [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'currency' => $account->currency->value,
                'parent_id' => $account->parent_id,
                'description' => $account->description,
                'is_active' => $account->is_active,
                'show_on_dashboard' => $account->show_on_dashboard,
                'is_system' => $account->is_system,
                'has_posted_movements' => $this->accountService->hasPostedMovements($account),
                'is_company_receivable' => $this->accountService->isCompanyReceivable($account),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        $this->accountService->update($account, $request->validated());

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    public function toggleDashboard(Account $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        $this->accountService->toggleDashboard($account);

        return back();
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $this->accountService->delete($account, $request->user());

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account deleted successfully.');
    }

    /**
     * @return array{date_from: mixed, date_to: mixed, voucher: mixed, description: mixed, amount: mixed}
     */
    private function ledgerFilters(AccountLedgerRequest $request): array
    {
        return [
            'date_from' => $request->validated('date_from'),
            'date_to' => $request->validated('date_to'),
            'voucher' => $request->validated('voucher'),
            'description' => $request->validated('description'),
            'amount' => $request->validated('amount'),
        ];
    }

    /**
     * @return array{search: string, type: string, currency: string}
     */
    private function chartFilters(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString(),
            'type' => $request->string('type')->toString(),
            'currency' => $request->string('currency')->toString(),
        ];
    }

    /**
     * @param  array{search?: string, type?: string, currency?: string}  $filters
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int}
     */
    private function chartPage(array $filters, int $page = 1): array
    {
        $paginator = $this->accountService
            ->paginate($filters, 30, $page)
            ->through(fn (Account $account) => $this->chartRow($account));

        return [
            'data' => array_values($paginator->items()),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function chartRow(Account $account): array
    {
        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'currency' => $account->currency->value,
            'parent' => $account->parent ? [
                'id' => $account->parent->id,
                'code' => $account->parent->code,
                'name' => $account->parent->name,
            ] : null,
            'is_system' => $account->is_system,
            'is_active' => $account->is_active,
            'show_on_dashboard' => $account->show_on_dashboard,
            'balance' => $this->accountService->balance($account),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'types' => collect(AccountType::cases())->map(fn (AccountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'currencies' => collect(Currency::cases())->map(fn (Currency $currency) => [
                'value' => $currency->value,
                'label' => $currency->label(),
            ]),
            'parents' => Account::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type', 'currency'])
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'label' => "{$account->code} — {$account->name}",
                    'type' => $account->type->value,
                    'currency' => $account->currency->value,
                ]),
        ];
    }
}
