<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('countries') && ! Schema::hasColumn('countries', 'latitude')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->decimal('latitude', 10, 7)->nullable()->after('iso_code');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            });
        }

        if (Schema::hasTable('land_trip_car_statuses') && ! Schema::hasColumn('land_trip_car_statuses', 'country_id')) {
            Schema::table('land_trip_car_statuses', function (Blueprint $table) {
                $table->foreignId('country_id')
                    ->nullable()
                    ->after('is_archive')
                    ->constrained('countries')
                    ->nullOnDelete();
            });
        }

        $now = now();
        $coords = [
            'AE' => [24.4538840, 54.3773438],
            'IR' => [32.4279080, 53.6880460],
            'IQ' => [33.2231910, 43.6792910],
            'TR' => [38.9637450, 35.2433220],
            'KW' => [29.3116600, 47.4817660],
            'OM' => [21.4735329, 55.9754130],
            'SA' => [23.8859420, 45.0791620],
            'UZ' => [41.3774910, 64.5852620],
        ];

        if (! DB::table('countries')->where('iso_code', 'UZ')->exists()) {
            DB::table('countries')->insert([
                'name' => 'Uzbekistan',
                'name_ar' => 'أوزبكستان',
                'iso_code' => 'UZ',
                'is_active' => true,
                'sort_order' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($coords as $iso => [$lat, $lng]) {
            DB::table('countries')->where('iso_code', $iso)->update([
                'latitude' => $lat,
                'longitude' => $lng,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasColumn('land_trip_car_statuses', 'country_id')) {
            $isoByCode = [
                'trip_to_bukhara' => 'UZ',
                'loaded_in_bukhara' => 'UZ',
                'trip_to_iran_bazargan' => 'IR',
                'from_iran_to_erbil' => 'IQ',
            ];

            foreach ($isoByCode as $code => $iso) {
                $countryId = DB::table('countries')->where('iso_code', $iso)->value('id');
                if ($countryId) {
                    DB::table('land_trip_car_statuses')->where('code', $code)->update(['country_id' => $countryId]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('land_trip_car_statuses', 'country_id')) {
            Schema::table('land_trip_car_statuses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('country_id');
            });
        }

        if (Schema::hasColumn('countries', 'latitude')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->dropColumn(['latitude', 'longitude']);
            });
        }
    }
};
