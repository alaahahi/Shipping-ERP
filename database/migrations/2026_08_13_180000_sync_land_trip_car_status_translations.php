<?php

use App\Services\LandTripCarStatusService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LandTripCarStatusService::class)->upsertDefaults();
    }

    public function down(): void
    {
        //
    }
};
