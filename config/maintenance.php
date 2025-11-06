<?php
// config/maintenance.php

/**
 * Maintenance & Cleanup Configuration
 *
 * Zephyr Framework uses a "cronless" approach for background tasks.
 * Tasks run probabilistically after response is sent to user.
 *
 * @author Ahmet ALTUN
 * @email ahmet.altun60@gmail.com
 * @github github.com/biyonik
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Lottery Configuration
    |--------------------------------------------------------------------------
    |
    | Probability settings for background task execution.
    |
    | Format: [wins, out_of]
    | Example: [1, 100] = 1% chance = runs ~once per 100 requests
    |
    | Recommendations:
    | - Low traffic (<1K req/day): [1, 50] (2%)
    | - Medium traffic (1K-10K): [1, 100] (1%)
    | - High traffic (>10K): [1, 500] (0.2%)
    |
    */
    'lottery' => [
        'probability' => (int) env('MAINTENANCE_LOTTERY_PROBABILITY', 1),
        'out_of' => (int) env('MAINTENANCE_LOTTERY_OUT_OF', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Execution Limits
    |--------------------------------------------------------------------------
    |
    | Safety limits to prevent cleanup tasks from consuming too many resources.
    |
    */
    'limits' => [
        // Maximum time (seconds) cleanup can run
        'max_execution_time' => 5,

        // Maximum files to clean per cycle
        'max_files_per_cycle' => 1000,

        // Minimum interval between cleanups (seconds)
        'min_interval' => 3600, // 1 hour

        // Force cleanup if not done in this time (seconds)
        'max_age_without_cleanup' => 86400, // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Tasks
    |--------------------------------------------------------------------------
    |
    | Cleanup tasks that will be executed during maintenance cycle.
    | Each task must be a callable (closure or [Class, 'method']).
    |
    */
    'tasks' => [
        [\Zephyr\Http\Middleware\RateLimitMiddleware::class, 'cleanup'],
        [\Zephyr\Cache\FileCache::class, 'cleanup'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable detailed logging for maintenance operations.
    | Useful for monitoring and debugging.
    |
    */
    'logging' => [
        'enabled' => env('MAINTENANCE_LOGGING', false),
        'channel' => 'maintenance', // Log file: storage/logs/maintenance.log
    ],
];