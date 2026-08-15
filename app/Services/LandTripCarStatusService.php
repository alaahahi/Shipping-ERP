<?php

namespace App\Services;

use App\Enums\LandTripCarRowTone;
use App\Models\LandTripCarStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LandTripCarStatusService
{
    public function seedDefaults(): void
    {
        if (LandTripCarStatus::query()->exists()) {
            return;
        }

        $this->upsertDefaults();
    }

    public function upsertDefaults(): void
    {
        $this->remapLegacyCodes();

        foreach ($this->defaultDefinitions() as $item) {
            LandTripCarStatus::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'name_ar' => $item['name_ar'],
                    'name_ckb' => $item['name_ckb'],
                    'row_tone' => $item['row_tone']->value,
                    'color' => $item['color'],
                    'match_aliases' => $item['match_aliases'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                    'is_archive' => $item['is_archive'] ?? false,
                ]
            );
        }
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     name_ar: string,
     *     name_ckb: string,
     *     row_tone: LandTripCarRowTone,
     *     color: string,
     *     match_aliases: list<string>,
     *     sort_order: int,
     *     is_archive?: bool
     * }>
     */
    public function defaultDefinitions(): array
    {
        return [
            [
                'code' => 'trip_to_bukhara',
                'name' => 'Trip to Bukhara',
                'name_ar' => 'رحلة إلى بخارى',
                'name_ckb' => 'گەشتە بۆخارا',
                'row_tone' => LandTripCarRowTone::Yellow,
                'color' => '#EAB308',
                'match_aliases' => ['گەشتە بۆخارا', 'گەشتە بوخارا', 'گشته بوخارا', 'trip to bukhara', 'trip to baghdad'],
                'sort_order' => 10,
            ],
            [
                'code' => 'loaded_in_bukhara',
                'name' => 'Loaded in Bukhara',
                'name_ar' => 'محمّل في بخارى',
                'name_ckb' => 'بارکرا لە بۆخارا',
                'row_tone' => LandTripCarRowTone::Yellow,
                'color' => '#F97316',
                'match_aliases' => ['بارکرا لە بۆخارا', 'باركرا لة بوخارا', 'loaded in bukhara', 'loaded in baghdad'],
                'sort_order' => 20,
            ],
            [
                'code' => 'trip_to_iran_bazargan',
                'name' => 'Trip to Iran Bazargan',
                'name_ar' => 'رحلة إلى إيران بازرغان',
                'name_ckb' => 'گەشتە ئێران بازرگان',
                'row_tone' => LandTripCarRowTone::Green,
                'color' => '#22C55E',
                'match_aliases' => ['گەشتە ئێران بازرگان', 'گشته ایران بازرگان', 'trip to iran bazargan'],
                'sort_order' => 30,
            ],
            [
                'code' => 'from_iran_to_erbil',
                'name' => 'From Iran towards Erbil',
                'name_ar' => 'من إيران باتجاه أربيل',
                'name_ckb' => 'لە ئێرانە بەرەو هەولێر',
                'row_tone' => LandTripCarRowTone::Green,
                'color' => '#0D9488',
                'match_aliases' => ['لە ئێرانە بەرەو هەولێر', 'له ایرانه بەرەو هەولێر', 'from iran to erbil'],
                'sort_order' => 40,
            ],
            [
                'code' => 'archived',
                'name' => 'Archive',
                'name_ar' => 'أرشفة',
                'name_ckb' => 'ئەرشیف',
                'row_tone' => LandTripCarRowTone::Neutral,
                'color' => '#64748B',
                'match_aliases' => ['أرشفة', 'ارشفة', 'archive', 'archived', 'ئەرشیف'],
                'sort_order' => 90,
                'is_archive' => true,
            ],
        ];
    }

    private function remapLegacyCodes(): void
    {
        LandTripCarStatus::query()->where('code', 'trip_to_baghdad')->update(['code' => 'trip_to_bukhara']);
        LandTripCarStatus::query()->where('code', 'loaded_in_baghdad')->update(['code' => 'loaded_in_bukhara']);
    }

    /**
     * @return Collection<int, LandTripCarStatus>
     */
    public function allActive(): Collection
    {
        return LandTripCarStatus::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array{id: int, code: string, label: string, row_tone: string}>
     */
    public function activeOptions(): array
    {
        return $this->allActive()
            ->map(fn (LandTripCarStatus $status) => [
                'id' => $status->id,
                'code' => $status->code,
                'name' => $status->name,
                'name_ar' => $status->name_ar,
                'name_ckb' => $status->name_ckb,
                'label' => $status->localizedName(),
                'row_tone' => $status->row_tone->value,
                'color' => $status->resolvedColor(),
                'is_archive' => (bool) $status->is_archive,
            ])
            ->all();
    }

    /**
     * @return list<int>
     */
    public function archiveStatusIds(): array
    {
        return LandTripCarStatus::query()
            ->where('is_archive', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transformMany(Collection $statuses): array
    {
        return $statuses
            ->map(fn (LandTripCarStatus $status) => $this->transform($status))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(LandTripCarStatus $status): array
    {
        return [
            'id' => $status->id,
            'code' => $status->code,
            'name' => $status->name,
            'name_ar' => $status->name_ar,
            'name_ckb' => $status->name_ckb,
            'label' => $status->localizedName(),
            'row_tone' => $status->row_tone->value,
            'row_tone_label' => $status->row_tone->label(),
            'color' => $status->resolvedColor(),
            'match_aliases' => $status->match_aliases ?? [],
            'sort_order' => $status->sort_order,
            'is_active' => $status->is_active,
            'is_archive' => (bool) $status->is_archive,
        ];
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     name_ar?: string|null,
     *     name_ckb?: string|null,
     *     row_tone: string,
     *     color: string,
     *     match_aliases?: list<string>|null,
     *     sort_order?: int|null,
     *     is_active?: bool
     * }  $data
     */
    public function create(array $data): LandTripCarStatus
    {
        return DB::transaction(function () use ($data): LandTripCarStatus {
            return LandTripCarStatus::query()->create([
                'code' => $this->normalizeCode($data['code']),
                'name' => trim($data['name']),
                'name_ar' => $this->nullableString($data['name_ar'] ?? null),
                'name_ckb' => $this->nullableString($data['name_ckb'] ?? null),
                'row_tone' => $data['row_tone'],
                'color' => $this->normalizeColor($data['color'] ?? null, $data['row_tone']),
                'match_aliases' => $this->normalizeAliases($data['match_aliases'] ?? []),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     name_ar?: string|null,
     *     name_ckb?: string|null,
     *     row_tone: string,
     *     color: string,
     *     match_aliases?: list<string>|null,
     *     sort_order?: int|null,
     *     is_active?: bool
     * }  $data
     */
    public function update(LandTripCarStatus $status, array $data): LandTripCarStatus
    {
        return DB::transaction(function () use ($status, $data): LandTripCarStatus {
            $status->update([
                'code' => $this->normalizeCode($data['code']),
                'name' => trim($data['name']),
                'name_ar' => $this->nullableString($data['name_ar'] ?? null),
                'name_ckb' => $this->nullableString($data['name_ckb'] ?? null),
                'row_tone' => $data['row_tone'],
                'color' => $this->normalizeColor($data['color'] ?? null, $data['row_tone']),
                'match_aliases' => $this->normalizeAliases($data['match_aliases'] ?? []),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => $status->is_archive ? true : ($data['is_active'] ?? true),
            ]);

            return $status->fresh();
        });
    }

    public function delete(LandTripCarStatus $status): void
    {
        if ($status->is_archive) {
            throw ValidationException::withMessages([
                'status' => 'The archive location cannot be deleted.',
            ]);
        }

        if ($status->cars()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Cannot delete a location status that is used on land trip cars.',
            ]);
        }

        $status->delete();
    }

    public function resolveByText(?string $text): ?LandTripCarStatus
    {
        $needle = $this->normalizeMatchText($text);
        if ($needle === '') {
            return null;
        }

        foreach ($this->allActive() as $status) {
            $candidates = array_filter([
                $status->name,
                $status->name_ar,
                $status->name_ckb,
                ...($status->match_aliases ?? []),
            ]);

            foreach ($candidates as $candidate) {
                if ($this->normalizeMatchText($candidate) === $needle) {
                    return $status;
                }
            }
        }

        foreach ($this->allActive() as $status) {
            $candidates = array_filter([
                $status->name,
                $status->name_ar,
                $status->name_ckb,
                ...($status->match_aliases ?? []),
            ]);

            foreach ($candidates as $candidate) {
                $normalized = $this->normalizeMatchText($candidate);
                if ($normalized !== '' && (str_contains($needle, $normalized) || str_contains($normalized, $needle))) {
                    return $status;
                }
            }
        }

        return null;
    }

    public function normalizeMatchText(?string $text): string
    {
        $value = trim((string) ($text ?? ''));
        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value, 'UTF-8');
        $replacements = [
            'ة' => 'ه',
            'ۀ' => 'ه',
            'ە' => 'ه',
            'ۆ' => 'و',
            'ك' => 'ک',
            'ي' => 'ی',
            'ى' => 'ی',
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ؤ' => 'و',
        ];

        return preg_replace('/\s+/u', ' ', strtr($value, $replacements)) ?? $value;
    }

    /**
     * @param  list<string>  $aliases
     * @return list<string>
     */
    private function normalizeAliases(array $aliases): array
    {
        $clean = [];
        foreach ($aliases as $alias) {
            $text = trim((string) $alias);
            if ($text !== '') {
                $clean[] = $text;
            }
        }

        return array_values(array_unique($clean));
    }

    private function normalizeCode(string $code): string
    {
        $slug = strtolower(trim($code));
        $slug = preg_replace('/[^a-z0-9_]+/', '_', $slug) ?? $slug;

        return trim($slug, '_');
    }

    private function normalizeColor(?string $color, string $rowTone): string
    {
        $value = strtoupper(trim((string) ($color ?? '')));
        if (preg_match('/^#[0-9A-F]{6}$/', $value) === 1) {
            return $value;
        }

        return match ($rowTone) {
            LandTripCarRowTone::Yellow->value => '#F59E0B',
            LandTripCarRowTone::Green->value => '#16A34A',
            default => '#64748B',
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
