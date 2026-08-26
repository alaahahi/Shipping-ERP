<?php

use App\Enums\LandTripCarDeletionSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_trip_car_deletions')) {
            Schema::create('land_trip_car_deletions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('cars_count')->default(0);
                $table->string('source', 32)->default(LandTripCarDeletionSource::Manual->value);
                $table->timestamp('restored_at')->nullable();
                $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'restored_at', 'id'], 'lt_car_del_company_restored_idx');
            });
        }

        if (! Schema::hasTable('land_trip_car_deletion_items')) {
            Schema::create('land_trip_car_deletion_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('land_trip_car_deletion_id')
                    ->constrained('land_trip_car_deletions')
                    ->cascadeOnDelete();
                $table->foreignId('land_trip_car_id')->nullable()->constrained('land_trip_cars')->nullOnDelete();
                $table->string('chassis_no', 64)->nullable();
                $table->string('model')->nullable();
                $table->string('cmr_waybill')->nullable();
                $table->timestamp('restored_at')->nullable();
                $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('land_trip_car_deletion_id', 'lt_car_del_items_deletion_idx');
                $table->index('land_trip_car_id', 'lt_car_del_items_car_idx');
            });
        }

        $this->backfillExistingSoftDeletes();
    }

    public function down(): void
    {
        Schema::dropIfExists('land_trip_car_deletion_items');
        Schema::dropIfExists('land_trip_car_deletions');
    }

    private function backfillExistingSoftDeletes(): void
    {
        if (! Schema::hasTable('land_trip_cars') || ! Schema::hasColumn('land_trip_cars', 'deleted_at')) {
            return;
        }

        $loggedCarIds = DB::table('land_trip_car_deletion_items')
            ->whereNotNull('land_trip_car_id')
            ->pluck('land_trip_car_id')
            ->all();

        $rows = DB::table('land_trip_cars as cars')
            ->join('land_trips as trips', 'trips.id', '=', 'cars.land_trip_id')
            ->whereNotNull('cars.deleted_at')
            ->when($loggedCarIds !== [], fn ($query) => $query->whereNotIn('cars.id', $loggedCarIds))
            ->orderBy('trips.company_id')
            ->orderBy('cars.deleted_at')
            ->orderBy('cars.id')
            ->get([
                'trips.company_id',
                'cars.id as car_id',
                'cars.chassis_no',
                'cars.model',
                'cars.cmr_waybill',
                'cars.deleted_at',
            ]);

        $groups = $rows->groupBy(fn ($row) => $row->company_id.'|'.$row->deleted_at);
        $now = now();

        foreach ($groups as $group) {
            $first = $group->first();
            $deletedAt = $first->deleted_at;
            $deletionId = DB::table('land_trip_car_deletions')->insertGetId([
                'company_id' => $first->company_id,
                'user_id' => null,
                'cars_count' => $group->count(),
                'source' => LandTripCarDeletionSource::Backfill->value,
                'restored_at' => null,
                'restored_by' => null,
                'created_at' => $deletedAt,
                'updated_at' => $now,
            ]);

            $items = $group->map(fn ($row) => [
                'land_trip_car_deletion_id' => $deletionId,
                'land_trip_car_id' => $row->car_id,
                'chassis_no' => $row->chassis_no,
                'model' => $row->model,
                'cmr_waybill' => $row->cmr_waybill,
                'restored_at' => null,
                'restored_by' => null,
                'created_at' => $deletedAt,
                'updated_at' => $now,
            ])->all();

            DB::table('land_trip_car_deletion_items')->insert($items);
        }
    }
};
