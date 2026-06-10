<?php

namespace App\Service;

class GeoService
{
    private const EARTH_RADIUS_M = 6371000;

    /**
     * Calculate distance between two GPS coordinates using Haversine formula.
     * Returns distance in meters.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_M * $c;
    }

    /**
     * Format distance for display.
     * < 1000m → "250m"
     * >= 1000m → "1.2km"
     */
    public function formatDistance(float $meters): string
    {
        if ($meters < 1000) {
            return round($meters) . 'm';
        }

        return number_format($meters / 1000, 1, '.', '') . 'km';
    }

    /**
     * Check if a point is within a given radius (meters) of a center.
     */
    public function isWithinRadius(float $centerLat, float $centerLon, float $pointLat, float $pointLon, float $radiusM): bool
    {
        return $this->calculateDistance($centerLat, $centerLon, $pointLat, $pointLon) <= $radiusM;
    }
}
