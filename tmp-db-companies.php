<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$companies = App\Models\Company::query()->orderBy('id')->get(['id', 'name']);
echo $companies->toJson(JSON_UNESCAPED_UNICODE).PHP_EOL;

foreach ($companies as $company) {
    $n = App\Models\LandTripCar::query()->whereHas('landTrip', fn ($q) => $q->where('company_id', $company->id))->count();
    echo "company {$company->id} {$company->name} cars={$n}\n";
}

$trip2 = App\Models\LandTrip::query()->withCount('cars')->find(2);
echo 'trip2='.json_encode($trip2?->only(['id', 'company_id', 'cmr_number', 'cars_count']), JSON_UNESCAPED_UNICODE).PHP_EOL;

$first = App\Models\LandTripCar::query()
    ->where('land_trip_id', 2)
    ->orderBy('sort_order')
    ->orderBy('id')
    ->limit(15)
    ->get(['id', 'chassis_no', 'description', 'sort_order', 'cmr_waybill']);
echo $first->toJson(JSON_UNESCAPED_UNICODE).PHP_EOL;

echo 'trip2 car count='.App\Models\LandTripCar::query()->where('land_trip_id', 2)->count().PHP_EOL;
echo 'min sort='.App\Models\LandTripCar::query()->whereHas('landTrip', fn ($q) => $q->where('company_id', 2))->min('sort_order').PHP_EOL;
