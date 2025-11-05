<?php

declare(strict_types=1);

namespace Zephyr\Support;

use JsonException;

/**
 * Maintenance Helper
 *
 * Provides utilities for cronless maintenance tasks.
 * Uses simple file-based tracking (no cache dependency required).
 *
 * @author Ahmet ALTUN
 * @email ahmet.altun60@gmail.com
 * @github github.com/biyonik
 */
class Maintenance
{
    /**
     * File to track last cleanup time
     */
    private const CLEANUP_MARKER = 'storage/framework/maintenance_last_cleanup';

    /**
     * Check if maintenance cleanup should run based on lottery
     */
    public static function shouldRun(): bool
    {
        $config = Config::get('maintenance.lottery');

        // Random int between 1 and out_of
        $roll = random_int(1, $config['out_of']);

        return $roll <= $config['probability'];
    }

    /**
     * Check if forced cleanup is needed (max age exceeded)
     */
    public static function needsForcedCleanup(): bool
    {
        $lastCleanup = self::getLastCleanupTime();
        $timeSinceCleanup = time() - $lastCleanup;
        $maxAge = Config::get('maintenance.limits.max_age_without_cleanup');

        return $timeSinceCleanup > $maxAge;
    }

    /**
     * Check if minimum interval has passed since last cleanup
     */
    public static function canRunCleanup(): bool
    {
        $lastCleanup = self::getLastCleanupTime();
        $timeSinceCleanup = time() - $lastCleanup;
        $minInterval = Config::get('maintenance.limits.min_interval');

        return $timeSinceCleanup >= $minInterval;
    }

    /**
     * Record cleanup execution time
     */
    public static function recordCleanup(): void
    {
        $file = self::getCleanupMarkerPath();
        $dir = dirname($file);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($file, (string)time());
    }

    /**
     * Get last cleanup timestamp
     */
    private static function getLastCleanupTime(): int
    {
        $file = self::getCleanupMarkerPath();

        if (!file_exists($file)) {
            return 0;
        }

        $contents = @file_get_contents($file);

        return $contents !== false ? (int)$contents : 0;
    }

    /**
     * Get cleanup marker file path
     */
    private static function getCleanupMarkerPath(): string
    {
        $basePath = Config::get('path.base') ?? getcwd();
        return $basePath . '/' . self::CLEANUP_MARKER;
    }

    /**
     * Log maintenance operation
     * @throws JsonException
     */
    public static function log(string $message, array $context = []): void
    {
        if (!Config::get('maintenance.logging.enabled')) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_THROW_ON_ERROR);

        error_log("[{$timestamp}] MAINTENANCE: {$message}{$contextStr}");
    }
}