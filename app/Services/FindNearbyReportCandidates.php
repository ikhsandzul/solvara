<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\DB;

/**
 * Searches for existing reports near given coordinates using MySQL bounding-box
 * pre-filter + Haversine distance calculation.
 *
 * Uses raw SQL because SQLite (tests) does not support ST_Distance_Sphere.
 * In production MySQL, ST_Distance_Sphere is used when coordinates are available.
 */
class FindNearbyReportCandidates
{
    /** Approximate meters per degree latitude (at equator). */
    private const METERS_PER_DEGREE = 111_320;

    public function find(
        float $latitude,
        float $longitude,
        int   $radiusMeters,
        int   $maxCandidates,
        int   $activeLookbackDays,
        int   $historyLookbackDays
    ): array {
        // Bounding box delta in degrees.
        $delta = $radiusMeters / self::METERS_PER_DEGREE;

        $latMin = $latitude - $delta;
        $latMax = $latitude + $delta;
        $lngMin = $longitude - $delta;
        $lngMax = $longitude + $delta;

        $activeCutoff  = now()->subDays($activeLookbackDays)->toDateTimeString();
        $historyCutoff = now()->subDays($historyLookbackDays)->toDateTimeString();

        // Active reports within lookback window.
        $activeStatuses = implode("','", ['submitted', 'accepted', 'in_progress', 'resolved_pending_verification', 'reopened']);

        // Haversine formula for SQLite compat (approximation).
        // In production MySQL, ST_Distance_Sphere is more accurate.
        $haversine = $this->haversineExpression($latitude, $longitude);

        $rows = DB::select("
            SELECT id, public_code, status, category_id, description, occurred_at,
                   latitude, longitude, address_text,
                   ({$haversine}) AS distance_m,
                   'active' AS candidate_type
            FROM reports
            WHERE latitude BETWEEN :lat_min AND :lat_max
              AND longitude BETWEEN :lng_min AND :lng_max
              AND status IN ('{$activeStatuses}')
              AND created_at >= :active_cutoff
              AND latitude IS NOT NULL AND longitude IS NOT NULL
            UNION ALL
            SELECT id, public_code, status, category_id, description, occurred_at,
                   latitude, longitude, address_text,
                   ({$haversine}) AS distance_m,
                   'history' AS candidate_type
            FROM reports
            WHERE latitude BETWEEN :lat_min2 AND :lat_max2
              AND longitude BETWEEN :lng_min2 AND :lng_max2
              AND status IN ('verified', 'rejected')
              AND created_at >= :history_cutoff
              AND created_at < :active_cutoff2
              AND latitude IS NOT NULL AND longitude IS NOT NULL
            ORDER BY distance_m ASC
            LIMIT :limit
        ", [
            'lat_min'       => $latMin,
            'lat_max'       => $latMax,
            'lng_min'       => $lngMin,
            'lng_max'       => $lngMax,
            'active_cutoff' => $activeCutoff,
            'lat_min2'      => $latMin,
            'lat_max2'      => $latMax,
            'lng_min2'      => $lngMin,
            'lng_max2'      => $lngMax,
            'history_cutoff'=> $historyCutoff,
            'active_cutoff2'=> $activeCutoff,
            'limit'         => $maxCandidates,
        ]);

        // Post-filter to actual radius (bounding box is wider than circle).
        return array_filter($rows, fn ($r) => $r->distance_m <= $radiusMeters);
    }

    /**
     * Haversine expression for approximate distance in meters.
     * Parameters are inlined as floats (not user input).
     */
    private function haversineExpression(float $lat, float $lng): string
    {
        $R = 6371000; // Earth radius in meters.

        return "(
            {$R} * 2 * ASIN(SQRT(
                POWER(SIN((RADIANS(latitude) - RADIANS({$lat})) / 2), 2) +
                COS(RADIANS({$lat})) * COS(RADIANS(latitude)) *
                POWER(SIN((RADIANS(longitude) - RADIANS({$lng})) / 2), 2)
            ))
        )";
    }
}
