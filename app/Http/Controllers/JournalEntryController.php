<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\JournalStatus;
use App\Http\Requests\Journals\StoreJournalEntryRequest;
use App\Http\Requests\Journals\UpdateJournalEntryRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\JournalService;
use App\Support\ApplicationTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'currency' => $request->string('currency')->toString(),
        ];

        $entries = $this->journalService
            ->paginate($filters)
            ->through(function (JournalEntry $entry) {
                $debit = $entry->lines->sum('debit');

                return [
                    'id' => $entry->id,
                    'voucher_number' => $entry->voucher_number,
                    'entry_date' => $entry->entry_date?->toDateString(),
                    'currency' => $entry->currency->value,
                    'reference' => $entry->reference,
                    'description' => $entry->description,
                    'status' => $entry->status->value,
                    'status_label' => $entry->status->label(),
                    'amount' => number_format((float) $debit, 2, '.', ''),
                    'created_by' => $entry->creator?->name,
                ];
            });

        return Inertia::render('Journals/Index', [
            'entries' => $entries,
            'filters' => $filters,
            'statuses' => collect(JournalStatus::cases())->map(fn (JournalStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'currencies' => collect(Currency::cases())->map(fn (Currency $currency) => [
                'value' => $currency->value,
                'label' => $currency->label(),
            ]),
            'canManage' => $request->user()?->can('accounting.manage') ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', JournalEntry::class);

        return Inertia::render('Journals/Create', $this->formOptions());
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $entry = $this->journalService->createDraft(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('journals.show', $entry)
            ->with('success', 'Draft journal entry created.');
    }

    public function show(JournalEntry $journal): Response
    {
        Gate::authorize('view', $journal);

        $journal->load(['lines.account:id,code,name,currency', 'creator:id,name', 'poster:id,name', 'voider:id,name']);

        return Inertia::render('Journals/Show', [
            'entry' => $this->transformEntry($journal),
            'canManage' => request()->user()?->can('accounting.manage') ?? false,
        ]);
    }

    public function print(JournalEntry $journal): Response
    {
        Gate::authorize('view', $journal);

        $journal->load(['lines.account:id,code,name,currency', 'creator:id,name']);

        return Inertia::render('Journals/Print', [
            'entry' => $this->transformEntry($journal),
            'printedAt' => ApplicationTimezone::formatNowLabel(),
        ]);
    }

    public function edit(JournalEntry $journal): Response
    {
        Gate::authorize('update', $journal);

        $journal->load('lines');

        return Inertia::render('Journals/Edit', [
            'entry' => [
                'id' => $journal->id,
                'voucher_number' => $journal->voucher_number,
                'entry_date' => $journal->entry_date?->toDateString(),
                'currency' => $journal->currency->value,
                'reference' => $journal->reference,
                'description' => $journal->description,
                'lines' => $journal->lines->map(fn ($line) => [
                    'account_id' => $line->account_id,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'memo' => $line->memo,
                ])->values(),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journal): RedirectResponse
    {
        Gate::authorize('update', $journal);

        $this->journalService->updateDraft($journal, $request->validated());

        return redirect()
            ->route('journals.show', $journal)
            ->with('success', 'Draft journal entry updated.');
    }

    public function post(Request $request, JournalEntry $journal): RedirectResponse
    {
        Gate::authorize('post', $journal);

        $this->journalService->post($journal, $request->user());

        return redirect()
            ->route('journals.show', $journal)
            ->with('success', 'Journal entry posted successfully.');
    }

    public function void(Request $request, JournalEntry $journal): RedirectResponse
    {
        Gate::authorize('void', $journal);

        $request->validate([
            'void_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->journalService->void(
            $journal,
            $request->user(),
            $request->string('void_reason')->toString() ?: null
        );

        return redirect()
            ->route('journals.show', $journal)
            ->with('success', 'Journal entry voided.');
    }

    public function reverse(Request $request, JournalEntry $journal): RedirectResponse
    {
        Gate::authorize('reverse', $journal);

        $reversal = $this->journalService->reverse($journal, $request->user());

        return redirect()
            ->route('journals.show', $reversal)
            ->with('success', 'Journal entry reversed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'currencies' => collect(Currency::cases())->map(fn (Currency $currency) => [
                'value' => $currency->value,
                'label' => $currency->label(),
            ]),
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'currency'])
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'label' => "{$account->code} — {$account->name} ({$account->currency->value})",
                    'currency' => $account->currency->value,
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformEntry(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'voucher_number' => $entry->voucher_number,
            'entry_date' => $entry->entry_date?->toDateString(),
            'currency' => $entry->currency->value,
            'reference' => $entry->reference,
            'description' => $entry->description,
            'status' => $entry->status->value,
            'status_label' => $entry->status->label(),
            'created_by' => $entry->creator?->name,
            'posted_by' => $entry->poster?->name,
            'posted_at' => ApplicationTimezone::formatDateTime($entry->posted_at),
            'voided_by' => $entry->voider?->name,
            'voided_at' => ApplicationTimezone::formatDateTime($entry->voided_at),
            'void_reason' => $entry->void_reason,
            'attachment_url' => $entry->attachmentUrl(),
            'total_debit' => number_format((float) $entry->lines->sum('debit'), 2, '.', ''),
            'total_credit' => number_format((float) $entry->lines->sum('credit'), 2, '.', ''),
            'lines' => $entry->lines->map(fn ($line) => [
                'id' => $line->id,
                'account' => $line->account ? [
                    'id' => $line->account->id,
                    'code' => $line->account->code,
                    'name' => $line->account->name,
                    'currency' => $line->account->currency->value,
                ] : null,
                'debit' => number_format((float) $line->debit, 2, '.', ''),
                'credit' => number_format((float) $line->credit, 2, '.', ''),
                'memo' => $line->memo,
            ]),
        ];
    }
}
