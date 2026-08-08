<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\VoyageCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoyageCompanyService
{
    public function __construct(
        private readonly CompanyService $companyService
    ) {}

    /**
     * @param  array{
     *     company_id?: int|null,
     *     company_name?: string|null,
     *     contact_name?: string|null,
     *     contact_phone?: string|null,
     *     shipping_price_per_car?: float|int|string,
     *     shipping_price_aed?: float|int|string,
     *     clearance_per_car?: float|int|string,
     *     notes?: string|null
     * }  $data
     */
    public function create(Voyage $voyage, array $data): VoyageCompany
    {
        $this->assertVoyageEditable($voyage);

        return DB::transaction(function () use ($voyage, $data): VoyageCompany {
            $company = $this->resolveCompany($data);

            if ($voyage->companies()->where('company_id', $company->id)->exists()) {
                throw ValidationException::withMessages([
                    'company_id' => 'This company is already linked to the voyage.',
                ]);
            }

            return $voyage->companies()->create([
                'company_id' => $company->id,
                'company_name' => $company->name,
                'contact_name' => $company->contact_name,
                'contact_phone' => $company->contact_phone,
                'shipping_price_per_car' => $data['shipping_price_per_car'] ?? 0,
                'shipping_price_aed' => $data['shipping_price_aed'] ?? 0,
                'clearance_per_car' => $data['clearance_per_car'] ?? 40,
                'notes' => $data['notes'] ?? null,
            ])->load('company');
        });
    }

    /**
     * @param  array{
     *     shipping_price_per_car?: float|int|string,
     *     shipping_price_aed?: float|int|string,
     *     clearance_per_car?: float|int|string,
     *     notes?: string|null
     * }  $data
     */
    public function update(VoyageCompany $company, array $data): VoyageCompany
    {
        $company->loadMissing('voyage', 'company');
        $this->assertVoyageEditable($company->voyage);

        return DB::transaction(function () use ($company, $data): VoyageCompany {
            $company->update([
                'shipping_price_per_car' => $data['shipping_price_per_car'] ?? 0,
                'shipping_price_aed' => $data['shipping_price_aed'] ?? 0,
                'clearance_per_car' => $data['clearance_per_car'] ?? 40,
                'notes' => $data['notes'] ?? null,
                // Keep snapshot aligned with master for display/history.
                'company_name' => $company->company?->name ?? $company->company_name,
                'contact_name' => $company->company?->contact_name ?? $company->contact_name,
                'contact_phone' => $company->company?->contact_phone ?? $company->contact_phone,
            ]);

            return $company->fresh('company');
        });
    }

    public function delete(VoyageCompany $company): void
    {
        $company->loadMissing('voyage');
        $this->assertVoyageEditable($company->voyage);
        $company->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(VoyageCompany $company): array
    {
        $company->loadMissing('company');

        return [
            'id' => $company->id,
            'voyage_id' => $company->voyage_id,
            'company_id' => $company->company_id,
            'company_name' => $company->company?->name ?? $company->company_name,
            'contact_name' => $company->company?->contact_name ?? $company->contact_name,
            'contact_phone' => $company->company?->contact_phone ?? $company->contact_phone,
            'shipping_price_per_car' => number_format((float) $company->shipping_price_per_car, 2, '.', ''),
            'shipping_price_aed' => number_format((float) $company->shipping_price_aed, 2, '.', ''),
            'clearance_per_car' => number_format((float) $company->clearance_per_car, 2, '.', ''),
            'unit_total_usd' => number_format($company->unitTotalUsd(), 2, '.', ''),
            'notes' => $company->notes,
            'excel_original_name' => $company->excel_original_name,
            'excel_uploaded_at' => $company->excel_uploaded_at?->format('Y-m-d H:i'),
            'excel_imported_count' => (int) $company->excel_imported_count,
            'has_excel' => filled($company->excel_file_path),
        ];
    }

    /**
     * @return list<array{id: int, label: string, contact_name: ?string, contact_phone: ?string}>
     */
    public function companyOptions(): array
    {
        return $this->companyService->options();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCompany(array $data): Company
    {
        if (! empty($data['company_id'])) {
            $company = Company::query()
                ->whereKey($data['company_id'])
                ->where('is_active', true)
                ->first();

            if (! $company) {
                throw ValidationException::withMessages([
                    'company_id' => 'Company not found.',
                ]);
            }

            return $company;
        }

        $name = trim((string) ($data['company_name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'company_name' => 'Select an existing company or enter a new company name.',
            ]);
        }

        return $this->companyService->create([
            'name' => $name,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'is_active' => true,
        ]);
    }

    private function assertVoyageEditable(?Voyage $voyage): void
    {
        if (! $voyage || ! $voyage->isEditable()) {
            throw ValidationException::withMessages([
                'voyage' => 'Closed voyages cannot accept company changes.',
            ]);
        }
    }
}
