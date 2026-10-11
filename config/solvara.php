<?php

return [
    /*
     * Maximum allowed upload size per attachment in kilobytes.
     * Default: 10240 = 10 MB
     */
    'max_attachment_kb' => env('REPORT_UPLOAD_MAX_KB', 10240),

    /*
     * Maximum number of images per submission.
     */
    'report_max_images' => env('REPORT_MAX_IMAGES', 3),

    /*
     * Hours before a temporary submission expires and photo is deleted.
     */
    'report_submission_temp_ttl_hours' => env('REPORT_SUBMISSION_TEMP_TTL_HOURS', 24),

    /*
     * Disk names for temporary (quarantine) and permanent photo storage.
     */
    'report_temp_disk'      => env('REPORT_TEMP_DISK', 'private'),
    'report_permanent_disk' => env('REPORT_PERMANENT_DISK', 'private'),

    /*
     * Spatial duplicate search configuration.
     */
    'duplicate_radius_meters'       => env('DUPLICATE_RADIUS_METERS', 100),
    'duplicate_max_candidates'      => env('DUPLICATE_MAX_CANDIDATES', 10),
    'duplicate_active_lookback_days'=> env('DUPLICATE_ACTIVE_LOOKBACK_DAYS', 180),
    'duplicate_history_lookback_days'=> env('DUPLICATE_HISTORY_LOOKBACK_DAYS', 365),
];
