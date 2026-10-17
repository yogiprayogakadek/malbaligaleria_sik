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
        'risk_monitoring_enabled',
        'auto_block_enabled',
        'risk_threshold',
        'risk_window_minutes',
        'block_duration_minutes',
        'risk_alerts_enabled',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'risk_monitoring_enabled' => 'boolean',
            'auto_block_enabled' => 'boolean',
            'risk_threshold' => 'integer',
            'risk_window_minutes' => 'integer',
            'block_duration_minutes' => 'integer',
            'risk_alerts_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self([
            'access_mode' => self::MODE_ANYWHERE,
            'risk_monitoring_enabled' => true,
            'auto_block_enabled' => true,
            'risk_threshold' => 5,
            'risk_window_minutes' => 15,
            'block_duration_minutes' => 60,
            'risk_alerts_enabled' => true,
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
