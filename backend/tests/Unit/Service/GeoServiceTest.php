<?php

namespace App\Tests\Unit\Service;

use App\Service\GeoService;
use PHPUnit\Framework\TestCase;

class GeoServiceTest extends TestCase
{
    private GeoService $geoService;

    protected function setUp(): void
    {
        $this->geoService = new GeoService();
    }

    public function testCalculateDistanceSamePoint(): void
    {
        $distance = $this->geoService->calculateDistance(48.8566, 2.3522, 48.8566, 2.3522);
        $this->assertEqualsWithDelta(0.0, $distance, 0.001);
    }

    public function testCalculateDistanceParisToLyon(): void
    {
        // Paris → Lyon ≈ 392 km
        $distance = $this->geoService->calculateDistance(48.8566, 2.3522, 45.7640, 4.8357);
        $this->assertGreaterThan(380000, $distance);
        $this->assertLessThan(405000, $distance);
    }

    public function testCalculateDistanceShortWalk(): void
    {
        // ~250 meters within Paris
        $distance = $this->geoService->calculateDistance(48.8566, 2.3522, 48.8588, 2.3510);
        $this->assertGreaterThan(100, $distance);
        $this->assertLessThan(500, $distance);
    }

    public function testFormatDistanceMeters(): void
    {
        $this->assertSame('250m', $this->geoService->formatDistance(250));
        $this->assertSame('999m', $this->geoService->formatDistance(999));
        $this->assertSame('0m', $this->geoService->formatDistance(0.4));
    }

    public function testFormatDistanceKilometers(): void
    {
        $this->assertSame('1.0km', $this->geoService->formatDistance(1000));
        $this->assertSame('1.5km', $this->geoService->formatDistance(1500));
        $this->assertSame('10.0km', $this->geoService->formatDistance(10000));
    }

    public function testIsWithinRadius(): void
    {
        // Chatelet to Saint-Lazare ~3km, should not be within 500m
        $withinSmall = $this->geoService->isWithinRadius(48.8596, 2.3473, 48.8750, 2.3250, 500);
        $this->assertFalse($withinSmall);

        // Same point — should be within 100m
        $withinLarge = $this->geoService->isWithinRadius(48.8566, 2.3522, 48.8566, 2.3522, 100);
        $this->assertTrue($withinLarge);
    }

    public function testFormatDistancePrecision(): void
    {
        $this->assertSame('1.2km', $this->geoService->formatDistance(1234));
        $this->assertSame('2.5km', $this->geoService->formatDistance(2500));
    }

    public function testEstimateWalkingDistanceAppliesDetourFactor(): void
    {
        $straightLine = $this->geoService->calculateDistance(48.8566, 2.3522, 48.8588, 2.3510);
        $walking = $this->geoService->estimateWalkingDistance(48.8566, 2.3522, 48.8588, 2.3510);

        $this->assertGreaterThan($straightLine, $walking, 'La distance de marche estimée doit être supérieure au vol d\'oiseau');
        $this->assertEqualsWithDelta($straightLine * 1.3, $walking, 0.01);
    }

    public function testApplyWalkingDetourFactorToKnownDistance(): void
    {
        $this->assertEqualsWithDelta(650.0, $this->geoService->applyWalkingDetourFactor(500), 0.01);
    }

    public function testEstimateWalkingDistanceSamePointIsZero(): void
    {
        $walking = $this->geoService->estimateWalkingDistance(48.8566, 2.3522, 48.8566, 2.3522);
        $this->assertEqualsWithDelta(0.0, $walking, 0.001);
    }
}
