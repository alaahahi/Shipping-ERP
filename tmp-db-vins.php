<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$vins = ['LC0C76C4XS4064445', 'LC0C76C45S4080780'];
$cars = App\Models\LandTripCar::query()->with('landTrip.company')->whereIn('chassis_no', $vins)->get();
echo 'land_trip_cars hits='.$cars->count().PHP_EOL;
foreach ($cars as $car) {
    echo json_encode([
        'id' => $car->id,
        'deleted' => $car->deleted_at,
        'chassis' => $car->chassis_no,
        'company' => $car->landTrip?->company?->name,
        'trip' => $car->land_trip_id,
        'status' => $car->location_status_id,
        'sort' => $car->sort_order,
    ], JSON_UNESCAPED_UNICODE).PHP_EOL;
}

$company = App\Models\Company::query()->where('name', 'like', '%BRWA%')->orWhere('name', 'like', '%KHOSHNAW%')->get(['id', 'name']);
echo 'companies='.$company->toJson(JSON_UNESCAPED_UNICODE).PHP_EOL;

if ($company->isNotEmpty()) {
    $id = $company->first()->id;
    $count = App\Models\LandTripCar::query()->whereHas('landTrip', fn ($q) => $q->where('company_id', $id))->count();
    $first = App\Models\LandTripCar::query()->whereHas('landTrip', fn ($q) => $q->where('company_id', $id))->orderBy('sort_order')->orderByDesc('id')->limit(8)->get(['id', 'chassis_no', 'description', 'sort_order', 'location_status_id']);
    echo "company cars=$count\n";
    echo $first->toJson(JSON_UNESCAPED_UNICODE).PHP_EOL;
}
