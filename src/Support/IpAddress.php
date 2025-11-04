<?php

declare(strict_types=1);

namespace Zephyr\Support;

/**
 * IP Address Utilities
 * 
 * Provides IP validation, CIDR matching, and proxy detection.
 * Used by Request class for secure IP address resolution.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class IpAddress
{
    /**
     * Check if an IP address is within a CIDR range
     * 
     * @param string $ip IP address to check
     * @param string $range CIDR range (e.g., "192.168.1.0/24")
     * @return bool
     */
    public static function inRange(string $ip, string $range): bool
    {
        // Handle wildcard (trust all - dangerous!)
        if ($range === '*') {
            return true;
        }
        
        // Single IP (no CIDR)
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }
        
        // Parse CIDR
        [$subnet, $mask] = explode('/', $range);
        
        // Convert to long integers for comparison
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);
        
        // Validation
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        
        // Check if IP is in subnet
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Check if IP is in any of the given ranges
     * 
     * @param string $ip IP address to check
     * @param array<string> $ranges Array of CIDR ranges
     * @return bool
     */
    public static function inRanges(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (static::inRange($ip, trim($range))) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate IP address
     * 
     * @param string $ip IP address to validate
     * @param bool $allowPrivate Allow private IP ranges (192.168.x.x, 10.x.x.x)
     * @param bool $allowReserved Allow reserved IP ranges (127.x.x.x)
     * @return bool
     */
    public static function isValid(
        string $ip, 
        bool $allowPrivate = true, 
        bool $allowReserved = true
    ): bool {
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        
        if (!$allowPrivate) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE;
        }
        
        if (!$allowReserved) {
            $flags |= FILTER_FLAG_NO_RES_RANGE;
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * Check if IP is private (RFC 1918)
     * 
     * @param string $ip IP address to check
     * @return bool
     */
    public static function isPrivate(string $ip): bool
    {
        return !filter_var(
            $ip, 
            FILTER_VALIDATE_IP, 
            FILTER_FLAG_NO_PRIV_RANGE
        );
    }

    /**
     * Check if IP is reserved (loopback, link-local, etc.)
     * 
     * @param string $ip IP address to check
     * @return bool
     */
    public static function isReserved(string $ip): bool
    {
        return !filter_var(
            $ip, 
            FILTER_VALIDATE_IP, 
            FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Parse X-Forwarded-For header chain
     * 
     * Returns array of IPs from left to right (as they appear in header).
     * Invalid IPs are filtered out.
     * 
     * @param string $header X-Forwarded-For header value
     * @return array<int, string> Array of valid IP addresses
     */
    public static function parseForwardedChain(string $header): array
    {
        // Split by comma and trim whitespace
        $ips = array_map('trim', explode(',', $header));
        
        // Filter out invalid IPs
        return array_values(array_filter($ips, fn($ip) => static::isValid($ip)));
    }

    /**
     * Get real client IP from forwarded chain
     * 
     * Traverses the X-Forwarded-For chain from right to left,
     * skipping trusted proxies, and returns the first untrusted IP.
     * 
     * Example:
     * - Header: "client, proxy1, proxy2"
     * - Trusted: [proxy1, proxy2]
     * - Returns: "client"
     * 
     * @param string $header X-Forwarded-For header value
     * @param array<string> $trustedProxies Trusted proxy IP ranges
     * @return string|null Real client IP or null if not found
     */
    public static function getRealIpFromChain(string $header, array $trustedProxies): ?string
    {
        $chain = static::parseForwardedChain($header);
        
        if (empty($chain)) {
            return null;
        }
        
        // Traverse from right to left (reverse proxy adds to right)
        $reversedChain = array_reverse($chain);
        
        foreach ($reversedChain as $ip) {
            // Skip trusted proxies
            if (static::inRanges($ip, $trustedProxies)) {
                continue;
            }
            
            // Found first untrusted IP = real client IP
            return $ip;
        }
        
        // All IPs in chain are trusted proxies, use leftmost (original)
        return $chain[0] ?? null;
    }
}