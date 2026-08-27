<?php

namespace App\Http\Requests\Reports;

use App\Services\LandTripCarReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LandTripCarReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewReports') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_ids' => $this->idList($this->input('country_ids', [])),
            'location_status_ids' => $this->idList($this->input('location_status_ids', [])),
            'chassis_text' => trim((string) $this->input('chassis_text', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country_ids' => ['nullable', 'array', 'max:100'],
            'country_ids.*' => ['integer', 'exists:countries,id'],
            'location_status_ids' => ['nullable', 'array', 'max:100'],
            'location_status_ids.*' => ['integer', 'exists:land_trip_car_statuses,id'],
            'chassis_text' => ['nullable', 'string', 'max:20000'],
            'duplicate_chassis' => ['nullable', 'array', 'max:300'],
            'duplicate_chassis.*' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->isExport()) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            if ($this->idList($this->input('country_ids', [])) === []
                && $this->idList($this->input('location_status_ids', [])) === []
                && $this->chassisNos() === []) {
                $validator->errors()->add(
                    'country_ids',
                    'Select a country, location, or paste chassis numbers before exporting.'
                );
            }
        });
    }

    /**
     * @return array{
     *     country_ids: list<int>,
     *     location_status_ids: list<int>,
     *     chassis_text: string,
     *     chassis_nos: list<string>,
     *     duplicate_chassis: list<string>
     * }
     */
    public function filters(): array
    {
        $inspect = $this->inspect();
        $wanted = array_flip($inspect['chassis_nos']);
        $hinted = [];

        $rawDuplicates = $this->input('duplicate_chassis', []);
        if (! is_array($rawDuplicates)) {
            $rawDuplicates = $rawDuplicates === null || $rawDuplicates === '' ? [] : [$rawDuplicates];
        }

        foreach ($rawDuplicates as $item) {
            $parsed = app(LandTripCarReportService::class)->parseChassisText((string) $item);
            $chassis = $parsed[0] ?? null;
            if ($chassis !== null && isset($wanted[$chassis])) {
                $hinted[] = $chassis;
            }
        }

        return [
            'country_ids' => $this->idList($this->input('country_ids', [])),
            'location_status_ids' => $this->idList($this->input('location_status_ids', [])),
            'chassis_text' => $inspect['cleaned_text'],
            'chassis_nos' => $inspect['chassis_nos'],
            'duplicate_chassis' => array_values(array_unique([...$inspect['duplicates'], ...$hinted])),
        ];
    }

    /**
     * @return array{chassis_nos: list<string>, duplicates: list<string>, cleaned_text: string}
     */
    private function inspect(): array
    {
        return app(LandTripCarReportService::class)->inspectChassisText(
            (string) $this->input('chassis_text', '')
        );
    }

    /**
     * @return list<string>
     */
    private function chassisNos(): array
    {
        return $this->inspect()['chassis_nos'];
    }

    private function isExport(): bool
    {
        return $this->routeIs(
            'reports.land-trips.export.excel',
            'reports.land-trips.export.pdf'
        );
    }

    /**
     * @return list<int>
     */
    private function idList(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = $raw === '' ? [] : preg_split('/\s*,\s*/', $raw);
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, is_array($raw) ? $raw : []),
            static fn (int $id): bool => $id > 0
        )));
    }
}
