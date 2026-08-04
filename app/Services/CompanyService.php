<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyService
{
    /**
     * @param  array{search?: string|null, active?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Company::query()->withCount('voyageCompanies')->latest('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (($filters['active'] ?? '') === '1') {
            $query->where('is_active', true);
        } elseif (($filters['active'] ?? '') === '0') {
            $query->where('is_active', false);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{
     *     name: string,
     *     contact_name?: string|null,
     *     contact_phone?: string|null,
     *     whatsapp_phone?: string|null,
     *     notify_whatsapp?: bool,
     *     email?: string|null,
     *     address?: string|null,
     *     notes?: string|null,
     *     is_active?: bool
     * }  $data
     */
    public function create(array $data): Company
    {
        return Company::query()->create([
            'name' => trim($data['name']),
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
            'notify_whatsapp' => $data['notify_whatsapp'] ?? false,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array{
     *     name: string,
     *     contact_name?: string|null,
     *     contact_phone?: string|null,
     *     whatsapp_phone?: string|null,
     *     notify_whatsapp?: bool,
     *     email?: string|null,
     *     address?: string|null,
     *     notes?: string|null,
     *     is_active?: bool
     * }  $data
     */
    public function update(Company $company, array $data): Company
    {
        $company->update([
            'name' => trim($data['name']),
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
            'notify_whatsapp' => $data['notify_whatsapp'] ?? $company->notify_whatsapp,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? $company->is_active,
        ]);

        $company->voyageCompanies()->update([
            'company_name' => $company->name,
            'contact_name' => $company->contact_name,
            'contact_phone' => $company->contact_phone,
        ]);

        return $company->fresh();
    }

    /**
     * @return list<array{id: int, label: string, contact_name: ?string, contact_phone: ?string, whatsapp_phone: ?string}>
     */
    public function options(): array
    {
        return Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'contact_name', 'contact_phone', 'whatsapp_phone'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'label' => $company->whatsappNumber()
                    ? "{$company->name} ({$company->whatsappNumber()})"
                    : $company->name,
                'contact_name' => $company->contact_name,
                'contact_phone' => $company->contact_phone,
                'whatsapp_phone' => $company->whatsapp_phone,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'contact_name' => $company->contact_name,
            'contact_phone' => $company->contact_phone,
            'whatsapp_phone' => $company->whatsapp_phone,
            'notify_whatsapp' => $company->notify_whatsapp,
            'email' => $company->email,
            'address' => $company->address,
            'notes' => $company->notes,
            'is_active' => $company->is_active,
            'voyages_count' => $company->voyage_companies_count
                ?? $company->voyageCompanies()->count(),
        ];
    }
}
