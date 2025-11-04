<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Zephyr\Support\IpAddress;

/**
 * IP Address Utility Tests
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class IpAddressTest extends TestCase
{
    public function testInRangeSingleIp(): void
    {
        $this->assertTrue(IpAddress::inRange('192.168.1.1', '192.168.1.1'));
        $this->assertFalse(IpAddress::inRange('192.168.1.2', '192.168.1.1'));
    }

    public function testInRangeCidr(): void
    {
        // Class C network
        $this->assertTrue(IpAddress::inRange('192.168.1.1', '192.168.1.0/24'));
        $this->assertTrue(IpAddress::inRange('192.168.1.255', '192.168.1.0/24'));
        $this->assertFalse(IpAddress::inRange('192.168.2.1', '192.168.1.0/24'));
        
        // Class B network
        $this->assertTrue(IpAddress::inRange('10.0.0.1', '10.0.0.0/8'));
        $this->assertTrue(IpAddress::inRange('10.255.255.255', '10.0.0.0/8'));
        $this->assertFalse(IpAddress::inRange('11.0.0.1', '10.0.0.0/8'));
    }

    public function testInRangeWildcard(): void
    {
        $this->assertTrue(IpAddress::inRange('1.2.3.4', '*'));
        $this->assertTrue(IpAddress::inRange('192.168.1.1', '*'));
    }

    public function testInRanges(): void
    {
        $ranges = ['192.168.1.0/24', '10.0.0.0/8', '172.16.0.0/12'];
        
        $this->assertTrue(IpAddress::inRanges('192.168.1.100', $ranges));
        $this->assertTrue(IpAddress::inRanges('10.50.30.20', $ranges));
        $this->assertTrue(IpAddress::inRanges('172.20.0.1', $ranges));
        $this->assertFalse(IpAddress::inRanges('8.8.8.8', $ranges));
    }

    public function testIsValid(): void
    {
        // Valid IPs
        $this->assertTrue(IpAddress::isValid('192.168.1.1'));
        $this->assertTrue(IpAddress::isValid('8.8.8.8'));
        $this->assertTrue(IpAddress::isValid('127.0.0.1'));
        
        // Invalid IPs
        $this->assertFalse(IpAddress::isValid('999.999.999.999'));
        $this->assertFalse(IpAddress::isValid('not-an-ip'));
        $this->assertFalse(IpAddress::isValid(''));
    }

    public function testIsValidWithFlags(): void
    {
        // Private IP
        $this->assertTrue(IpAddress::isValid('192.168.1.1', allowPrivate: true));
        $this->assertFalse(IpAddress::isValid('192.168.1.1', allowPrivate: false));
        
        // Reserved IP (loopback)
        $this->assertTrue(IpAddress::isValid('127.0.0.1', allowReserved: true));
        $this->assertFalse(IpAddress::isValid('127.0.0.1', allowReserved: false));
    }

    public function testIsPrivate(): void
    {
        $this->assertTrue(IpAddress::isPrivate('192.168.1.1'));
        $this->assertTrue(IpAddress::isPrivate('10.0.0.1'));
        $this->assertTrue(IpAddress::isPrivate('172.16.0.1'));
        $this->assertFalse(IpAddress::isPrivate('8.8.8.8'));
    }

    public function testIsReserved(): void
    {
        $this->assertTrue(IpAddress::isReserved('127.0.0.1'));
        $this->assertTrue(IpAddress::isReserved('0.0.0.0'));
        $this->assertFalse(IpAddress::isReserved('8.8.8.8'));
    }

    public function testParseForwardedChain(): void
    {
        $chain = IpAddress::parseForwardedChain('203.0.113.1, 104.16.0.1, 192.168.1.1');
        
        $this->assertCount(3, $chain);
        $this->assertSame('203.0.113.1', $chain[0]);
        $this->assertSame('104.16.0.1', $chain[1]);
        $this->assertSame('192.168.1.1', $chain[2]);
    }

    public function testGetRealIpFromChain(): void
    {
        $trustedProxies = ['104.16.0.0/13', '192.168.1.0/24'];
        
        // Client → Cloudflare → Local Proxy → Server
        // 203.0.113.1, 104.16.0.1, 192.168.1.100
        $realIp = IpAddress::getRealIpFromChain(
            '203.0.113.1, 104.16.0.1, 192.168.1.100',
            $trustedProxies
        );
        
        $this->assertSame('203.0.113.1', $realIp);
    }

    public function testGetRealIpFromChainAllTrusted(): void
    {
        $trustedProxies = ['*'];
        
        // All IPs are trusted, return leftmost (original client)
        $realIp = IpAddress::getRealIpFromChain(
            '203.0.113.1, 104.16.0.1',
            $trustedProxies
        );
        
        $this->assertSame('203.0.113.1', $realIp);
    }
}