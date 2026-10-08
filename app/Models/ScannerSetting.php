<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScannerSetting extends Model
{
    public const MODE_ANYWHERE = 'anywhere';

    public const MODE_GEOFENCE = 'geofence';

    protected $fillable = [
        'access_mode',
        'latitude',
        'longitude',
        'radius_meters',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self([
            'access_mode' => self::MODE_ANYWHERE,
        ]);
    }

    public function requiresLocation(): bool
    {
        return $this->access_mode === self::MODE_GEOFENCE;
    }

    public function distanceFrom(float $latitude, float $longitude): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitude - (float) $this->latitude);
        $longitudeDelta = deg2rad($longitude - (float) $this->longitude);
        $originLatitude = deg2rad((float) $this->latitude);
        $deviceLatitude = deg2rad($latitude);

        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($originLatitude) * cos($deviceLatitude) * sin($longitudeDelta / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($haversine)));
    }
}
