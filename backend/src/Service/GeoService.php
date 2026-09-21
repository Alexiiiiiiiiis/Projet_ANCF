<?php

namespace App\Service;

class GeoService
{
    private const EARTH_RADIUS_M = 6371000;

    // F6.4 du cahier des charges demande une "distance à pied", pas à vol d'oiseau. Sans moteur
    // de routing piéton (OSRM/Mapbox Directions — une nouvelle dépendance externe avec son propre
    // quota, ce qu'on a déjà appris à nos dépens avec l'API IDFM), on approxime avec un facteur de
    // détour : le ratio moyen constaté entre distance de marche réelle et distance à vol d'oiseau
    // en tissu urbain dense comme Paris (rues, blocs, obstacles). 1.3 est une valeur usuelle en
    // urbanisme pour ce type de contexte (contre ~1.0 en zone ouverte/rase campagne).
    private const WALKING_DETOUR_FACTOR = 1.3;

    /**
     * Distance entre deux coordonnées GPS, calculée avec la formule de Haversine.
     * Renvoie la distance en mètres (à vol d'oiseau).
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

    /** Distance de marche estimée (mètres) — vol d'oiseau corrigé d'un facteur de détour urbain. */
    public function estimateWalkingDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return $this->calculateDistance($lat1, $lon1, $lat2, $lon2) * self::WALKING_DETOUR_FACTOR;
    }

    /** Applique le même facteur de détour à une distance à vol d'oiseau déjà connue (ex. fournie par une API tierce). */
    public function applyWalkingDetourFactor(float $straightLineMeters): float
    {
        return $straightLineMeters * self::WALKING_DETOUR_FACTOR;
    }

    /**
     * Formate une distance pour l'affichage.
     * < 1000m → "250m"
     * >= 1000m → "1.2km".
     */
    public function formatDistance(float $meters): string
    {
        if ($meters < 1000) {
            return round($meters).'m';
        }

        return number_format($meters / 1000, 1, '.', '').'km';
    }

    /**
     * Vérifie si un point est dans un rayon donné (en mètres) autour d'un centre.
     */
    public function isWithinRadius(float $centerLat, float $centerLon, float $pointLat, float $pointLon, float $radiusM): bool
    {
        return $this->calculateDistance($centerLat, $centerLon, $pointLat, $pointLon) <= $radiusM;
    }
}
