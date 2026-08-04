<?php

namespace App\Services;

use App\Models\Voyage;
use App\Models\VoyageRoute;
use App\Models\VoyageWaypoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoyageTrackingService
{
    public const DEFAULT_PORTS = [
        'Jebel Ali, Dubai' => ['lat' => 25.0264, 'lng' => 55.0678],
        'Rajid' => ['lat' => 25.0264, 'lng' => 55.0678],
        'Bandar Abbas' => ['lat' => 27.1468, 'lng' => 56.2828],
        'Bandar Abbas, Iran' => ['lat' => 27.1468, 'lng' => 56.2828],
    ];

    /**
     * @return array{waypoints: list<array<string, mixed>>, routes: list<array<string, mixed>>}
     */
    public function tracking(Voyage $voyage): array
    {
        $waypoints = $voyage->waypoints->map(fn (VoyageWaypoint $wp) => [
            'id' => $wp->id,
            'name' => $wp->name,
            'reached_at' => $wp->reached_at?->format('Y-m-d H:i'),
            'latitude' => $wp->latitude,
            'longitude' => $wp->longitude,
            'sort_order' => $wp->sort_order,
            'notes' => $wp->notes,
        ])->values()->all();

        $routes = $voyage->routes->map(fn (VoyageRoute $route) => [
            'route_type' => $route->route_type,
            'color' => $route->color,
            'label' => $route->label,
            'coordinates' => $route->coordinates,
        ])->values()->all();

        if ($routes === []) {
            $routes = $this->defaultRoutes($voyage);
        }

        if ($waypoints === []) {
            $waypoints = $this->defaultWaypoints($voyage, $routes);
        }

        return [
            'waypoints' => $waypoints,
            'routes' => $routes,
        ];
    }

    /**
     * @param  list<array{name: string, reached_at?: string|null, latitude?: float|null, longitude?: float|null, sort_order?: int, notes?: string|null}>  $data
     */
    public function syncWaypoints(Voyage $voyage, array $data): void
    {
        DB::transaction(function () use ($voyage, $data): void {
            $voyage->waypoints()->delete();
            $voyage->waypoints()->createMany($data);
        });
    }

    /**
     * @param  list<array{route_type: string, coordinates: list<array{lat: float, lng: float}>, color?: string, label?: string}>  $data
     */
    public function syncRoutes(Voyage $voyage, array $data): void
    {
        DB::transaction(function () use ($voyage, $data): void {
            $voyage->routes()->delete();
            foreach ($data as $route) {
                $voyage->routes()->create($route);
            }
        });
    }

    /**
     * @param  array{name: string, reached_at?: string|null, latitude?: float|null, longitude?: float|null, sort_order?: int, notes?: string|null}  $data
     */
    public function addWaypoint(Voyage $voyage, array $data): VoyageWaypoint
    {
        return $voyage->waypoints()->create($data);
    }

    public function deleteWaypoint(VoyageWaypoint $waypoint): void
    {
        $waypoint->delete();
    }

    /**
     * @return list<array{route_type: string, color: string, label: string, coordinates: list<array{lat: float, lng: float}>}>
     */
    private function defaultRoutes(Voyage $voyage): array
    {
        $pol = self::DEFAULT_PORTS[$voyage->pol] ?? null;
        $pod = self::DEFAULT_PORTS[$voyage->pod] ?? null;

        if ($pol && $pod) {
            return [
                [
                    'route_type' => 'sea',
                    'color' => '#0d9488',
                    'label' => 'Sea route',
                    'coordinates' => $this->seaRoute($pol['lat'], $pol['lng'], $pod['lat'], $pod['lng']),
                ],
            ];
        }

        return [];
    }

    /**
     * @param  list<array{route_type: string, color: string, label: string, coordinates: list<array{lat: float, lng: float}>}>  $routes
     * @return list<array<string, mixed>>
     */
    private function defaultWaypoints(Voyage $voyage, array $routes): array
    {
        $pol = self::DEFAULT_PORTS[$voyage->pol] ?? null;
        $pod = self::DEFAULT_PORTS[$voyage->pod] ?? null;

        $waypoints = [];

        if ($pol) {
            $waypoints[] = $this->makeWaypoint('Departure — '.$voyage->pol, $pol['lat'], $pol['lng'], 0, $voyage->sailing_date?->format('Y-m-d H:i'));
        }

        if ($pod) {
            $waypoints[] = $this->makeWaypoint('Arrival — '.$voyage->pod, $pod['lat'], $pod['lng'], 1, $voyage->arrival_date?->format('Y-m-d H:i'));
        }

        if ($waypoints === [] && $routes !== []) {
            $first = $routes[0]['coordinates'][0] ?? null;
            $last = $routes[0]['coordinates'][count($routes[0]['coordinates']) - 1] ?? null;
            if ($first) {
                $waypoints[] = $this->makeWaypoint('Departure', $first['lat'], $first['lng'], 0);
            }
            if ($last) {
                $waypoints[] = $this->makeWaypoint('Arrival', $last['lat'], $last['lng'], 1);
            }
        }

        return $waypoints;
    }

    private function makeWaypoint(string $name, float $lat, float $lng, int $sortOrder, ?string $reachedAt = null): array
    {
        return [
            'id' => null,
            'name' => $name,
            'latitude' => $lat,
            'longitude' => $lng,
            'sort_order' => $sortOrder,
            'reached_at' => $reachedAt,
            'notes' => null,
        ];
    }

    /**
     * @return list<array{lat: float, lng: float}>
     */
    private function seaRoute(float $lat1, float $lng1, float $lat2, float $lng2): array
    {
        $points = [];
        $steps = 10;
        for ($i = 0; $i <= $steps; $i++) {
            $ratio = $i / $steps;
            $lat = $lat1 + ($lat2 - $lat1) * $ratio;
            $lng = $lng1 + ($lng2 - $lng1) * $ratio;
            // Slight curve to simulate sea route (avoid straight line over land)
            if ($i > 0 && $i < $steps) {
                $offset = sin($ratio * pi()) * 0.8;
                $lng += $offset;
            }
            $points[] = ['lat' => round($lat, 5), 'lng' => round($lng, 5)];
        }

        return $points;
    }
}
