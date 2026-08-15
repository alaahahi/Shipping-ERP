<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cars = App\Models\LandTripCar::query()
    ->whereHas('landTrip', fn ($q) => $q->where('company_id', 1))
    ->with('landTrip:id,company_id,cmr_number')
    ->orderBy('sort_order')
    ->orderBy('id')
    ->get(['id', 'land_trip_id', 'chassis_no', 'description', 'sort_order', 'cmr_waybill', 'location_status_id']);

echo 'total='.$cars->count().PHP_EOL;
$byTrip = $cars->groupBy('land_trip_id')->map->count();
echo 'byTrip='.$byTrip->toJson().PHP_EOL;
echo "first 5:\n".$cars->take(5)->toJson(JSON_UNESCAPED_UNICODE).PHP_EOL;

$notTrip2 = $cars->where('land_trip_id', '!=', 2);
echo "not trip2:\n".$notTrip2->values()->toJson(JSON_UNESCAPED_UNICODE).PHP_EOL;
