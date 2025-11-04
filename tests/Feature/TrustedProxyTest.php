<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Zephyr\Http\Request;
use Zephyr\Support\Config;

/**
 * Trusted Proxy Integration Tests
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class TrustedProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clear config after each test
        Config::clear();
        parent::tearDown();
    }

    public function testDirectConnectionIgnoresHeaders(): void
    {
        // No trusted proxies configured
        Config::set('trustedproxy.proxies', []);
        
        // Request from client with fake header
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: ['X-Forwarded-For' => '1.2.3.4'],
            query: [],
            body: [],
            files: [],
            server: [
                'REMOTE_ADDR' => '203.0.113.45',
                'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
            ],
            cookies: []
        );
        
        // Should ignore fake header and use REMOTE_ADDR
        $this->assertSame('203.0.113.45', $request->ip());
    }

    public function testUntrustedProxyIgnoresHeaders(): void
    {
        // Trust only Cloudflare
        Config::set('trustedproxy.proxies', ['104.16.0.0/13']);
        Config::set('trustedproxy.headers', ['X_FORWARDED_FOR']);
        
        // Request from untrusted proxy
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: [],
            body: [],
            files: [],
            server: [
                'REMOTE_ADDR' => '8.8.8.8',  // Not in trusted range
                'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
            ],
            cookies: []
        );
        
        // Should ignore header from untrusted proxy
        $this->assertSame('8.8.8.8', $request->ip());
    }

    public function testTrustedProxyUsesHeaders(): void
    {
        // Trust Cloudflare
        Config::set('trustedproxy.proxies', ['104.16.0.0/13']);
        Config::set('trustedproxy.headers', ['X_FORWARDED_FOR']);
        
        // Request through Cloudflare
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: [],
            body: [],
            files: [],
            server: [
                'REMOTE_ADDR' => '104.16.0.1',  // Cloudflare IP
                'HTTP_X_FORWARDED_FOR' => '203.0.113.45',  // Real client
            ],
            cookies: []
        );
        
        // Should trust header and return real client IP
        $this->assertSame('203.0.113.45', $request->ip());
    }

    public function testMultipleProxyChain(): void
    {
        Config::set('trustedproxy.proxies', ['104.16.0.0/13', '192.168.1.0/24']);
        Config::set('trustedproxy.headers', ['X_FORWARDED_FOR']);
        
        // Client → Cloudflare → Local Proxy → Server
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: [],
            body: [],
            files: [],
            server: [
                'REMOTE_ADDR' => '192.168.1.100',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.45, 104.16.0.1, 192.168.1.100',
            ],
            cookies: []
        );
        
        // Should skip trusted proxies and return real client
        $this->assertSame('203.0.113.45', $request->ip());
    }

    public function testSpoofedHeaderFromUntrustedProxy(): void
    {
        Config::set('trustedproxy.proxies', ['192.168.1.100']);
        Config::set('trustedproxy.headers', ['X_FORWARDED_FOR']);
        
        // Attacker tries to spoof from wrong IP
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: [],
            body: [],
            files: [],
            server: [
                'REMOTE_ADDR' => '203.0.113.99',  // Attacker IP
                'HTTP_X_FORWARDED_FOR' => '127.0.0.1',  // Fake admin IP
            ],
            cookies: []
        );
        
        // Should ignore spoofed header
        $this->assertSame('203.0.113.99', $request->ip());
    }
}