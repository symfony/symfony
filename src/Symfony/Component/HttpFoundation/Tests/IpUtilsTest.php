<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\IpUtils;

class IpUtilsTest extends TestCase
{
    public function testSeparateCachesPerProtocol()
    {
        $ip = '192.168.52.1';
        $subnet = '192.168.0.0/16';

        $this->assertFalse(IpUtils::checkIp6($ip, $subnet));
        $this->assertTrue(IpUtils::checkIp4($ip, $subnet));

        $ip = '2a01:198:603:0:396e:4789:8e99:890f';
        $subnet = '2a01:198:603:0::/65';

        $this->assertFalse(IpUtils::checkIp4($ip, $subnet));
        $this->assertTrue(IpUtils::checkIp6($ip, $subnet));
    }

    /**
     * @dataProvider getIpv4Data
     */
    public function testIpv4($matches, $remoteAddr, $cidr)
    {
        $this->assertSame($matches, IpUtils::checkIp($remoteAddr, $cidr));
    }

    public static function getIpv4Data()
    {
        return [
            [true, '192.168.1.1', '192.168.1.1'],
            [true, '192.168.1.1', '192.168.1.1/1'],
            [true, '192.168.1.1', '192.168.1.0/24'],
            [false, '192.168.1.1', '1.2.3.4/1'],
            [false, '192.168.1.1', '192.168.1.1/33'], // invalid subnet
            [true, '192.168.1.1', ['1.2.3.4/1', '192.168.1.0/24']],
            [true, '192.168.1.1', ['192.168.1.0/24', '1.2.3.4/1']],
            [false, '192.168.1.1', ['1.2.3.4/1', '4.3.2.1/1']],
            [true, '1.2.3.4', '0.0.0.0/0'],
            [true, '1.2.3.4', '192.168.1.0/0'],
            [false, '1.2.3.4', '256.256.256/0'], // invalid CIDR notation
            [false, 'an_invalid_ip', '192.168.1.0/24'],
            [false, '', '1.2.3.4/1'],
        ];
    }

    /**
     * @dataProvider getIpv6Data
     */
    public function testIpv6($matches, $remoteAddr, $cidr)
    {
        if (!\defined('AF_INET6')) {
            $this->markTestSkipped('Only works when PHP is compiled without the option "disable-ipv6".');
        }

        $this->assertSame($matches, IpUtils::checkIp($remoteAddr, $cidr));
    }

    public static function getIpv6Data()
    {
        return [
            [true, '2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'],
            [false, '2a00:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'],
            [false, '2a01:198:603:0:396e:4789:8e99:890f', '::1'],
            [true, '0:0:0:0:0:0:0:1', '::1'],
            [false, '0:0:603:0:396e:4789:8e99:0001', '::1'],
            [true, '0:0:603:0:396e:4789:8e99:0001', '::/0'],
            [true, '0:0:603:0:396e:4789:8e99:0001', '2a01:198:603:0::/0'],
            [true, '2a01:198:603:0:396e:4789:8e99:890f', ['::1', '2a01:198:603:0::/65']],
            [true, '2a01:198:603:0:396e:4789:8e99:890f', ['2a01:198:603:0::/65', '::1']],
            [false, '2a01:198:603:0:396e:4789:8e99:890f', ['::1', '1a01:198:603:0::/65']],
            [false, '}__test|O:21:&quot;JDatabaseDriverMysqli&quot;:3:{s:2', '::1'],
            [false, '2a01:198:603:0:396e:4789:8e99:890f', 'unknown'],
            [false, '', '::1'],
            [false, '127.0.0.1', '::1'],
            [false, '0.0.0.0/8', '::1'],
            [false,  '::1', '127.0.0.1'],
            [false,  '::1', '0.0.0.0/8'],
            [true, '::ffff:10.126.42.2', '::ffff:10.0.0.0/0'],
        ];
    }

    /**
     * @requires extension sockets
     */
    public function testAnIpv6WithOptionDisabledIpv6()
    {
        $this->expectException(\RuntimeException::class);
        if (\defined('AF_INET6')) {
            $this->markTestSkipped('Only works when PHP is compiled with the option "disable-ipv6".');
        }

        IpUtils::checkIp('2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65');
    }

    /**
     * @dataProvider invalidIpAddressData
     */
    public function testInvalidIpAddressesDoNotMatch($requestIp, $proxyIp)
    {
        $this->assertFalse(IpUtils::checkIp4($requestIp, $proxyIp));
    }

    public static function invalidIpAddressData()
    {
        return [
            'invalid proxy wildcard' => ['192.168.20.13', '*'],
            'invalid proxy missing netmask' => ['192.168.20.13', '0.0.0.0'],
            'invalid request IP with invalid proxy wildcard' => ['0.0.0.0', '*'],
        ];
    }

    /**
     * @dataProvider anonymizedIpData
     */
    public function testAnonymize($ip, $expected)
    {
        $this->assertSame($expected, IpUtils::anonymize($ip));
    }

    public static function anonymizedIpData()
    {
        return [
            ['192.168.1.1', '192.168.1.0'],
            ['1.2.3.4', '1.2.3.0'],
            ['2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603::'],
            ['2a01:198:603:10:396e:4789:8e99:890f', '2a01:198:603:10::'],
            ['::1', '::'],
            ['0:0:0:0:0:0:0:1', '::'],
            ['1:0:0:0:0:0:0:1', '1::'],
            ['0:0:603:50:396e:4789:8e99:0001', '0:0:603:50::'],
            ['[0:0:603:50:396e:4789:8e99:0001]', '[0:0:603:50::]'],
            ['[2a01:198::3]', '[2a01:198::]'],
            ['::ffff:123.234.235.236', '::ffff:123.234.235.0'], // IPv4-mapped IPv6 addresses
            ['::123.234.235.236', '::123.234.235.0'], // deprecated IPv4-compatible IPv6 address
            ['fe80::1fc4:15d8:78db:2319%enp4s0', 'fe80::'], // IPv6 link-local with RFC4007 scoping
        ];
    }

    /**
     * @dataProvider anonymizedIpDataWithBytes
     */
    public function testAnonymizeWithBytes($ip, $expected, $bytesForV4, $bytesForV6)
    {
        $this->assertSame($expected, IpUtils::anonymize($ip, $bytesForV4, $bytesForV6));
    }

    public static function anonymizedIpDataWithBytes(): array
    {
        return [
            ['192.168.1.1', '192.168.0.0', 2, 8],
            ['192.168.1.1', '192.0.0.0', 3, 8],
            ['192.168.1.1', '0.0.0.0', 4, 8],
            ['1.2.3.4', '1.2.3.0', 1, 8],
            ['1.2.3.4', '1.2.3.4', 0, 8],
            ['2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0:396e:4789:8e99:890f', 1, 0],
            ['2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0:396e:4789::', 1, 4],
            ['2a01:198:603:10:396e:4789:8e99:890f', '2a01:198:603:10:396e:4700::', 1, 5],
            ['2a01:198:603:10:396e:4789:8e99:890f', '2a00::', 1, 15],
            ['2a01:198:603:10:396e:4789:8e99:890f', '::', 1, 16],
            ['::1', '::', 1, 1],
            ['0:0:0:0:0:0:0:1', '::', 1, 1],
            ['1:0:0:0:0:0:0:1', '1::', 1, 1],
            ['0:0:603:50:396e:4789:8e99:0001', '0:0:603::', 1, 10],
            ['[0:0:603:50:396e:4789:8e99:0001]', '[::603:50:396e:4789:8e00:0]', 1, 3],
            ['[2a01:198::3]', '[2a01:198::]', 1, 2],
            ['::ffff:123.234.235.236', '::ffff:123.234.235.0', 1, 8], // IPv4-mapped IPv6 addresses
            ['::123.234.235.236', '::123.234.0.0', 2, 8], // deprecated IPv4-compatible IPv6 address
        ];
    }

    public function testAnonymizeV4WithNegativeBytes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot anonymize less than 0 bytes.');

        IpUtils::anonymize('anything', -1, 8);
    }

    public function testAnonymizeV6WithNegativeBytes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot anonymize less than 0 bytes.');

        IpUtils::anonymize('anything', 1, -1);
    }

    public function testAnonymizeV4WithTooManyBytes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot anonymize more than 4 bytes for IPv4 and 16 bytes for IPv6.');

        IpUtils::anonymize('anything', 5, 8);
    }

    public function testAnonymizeV6WithTooManyBytes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot anonymize more than 4 bytes for IPv4 and 16 bytes for IPv6.');

        IpUtils::anonymize('anything', 1, 17);
    }

    /**
     * @dataProvider getIp4SubnetMaskZeroData
     */
    public function testIp4SubnetMaskZero($matches, $remoteAddr, $cidr)
    {
        $this->assertSame($matches, IpUtils::checkIp4($remoteAddr, $cidr));
    }

    public static function getIp4SubnetMaskZeroData()
    {
        return [
            [true, '1.2.3.4', '0.0.0.0/0'],
            [true, '1.2.3.4', '192.168.1.0/0'],
            [false, '1.2.3.4', '256.256.256/0'], // invalid CIDR notation
        ];
    }

    /**
     * @dataProvider getIsPrivateIpData
     */
    public function testIsPrivateIp(string $ip, bool $matches)
    {
        $this->assertSame($matches, IpUtils::isPrivateIp($ip));
    }

    public static function getIsPrivateIpData(): array
    {
        return [
            // private
            ['127.0.0.1',       true],
            ['10.0.0.1',        true],
            ['192.168.0.1',     true],
            ['172.16.0.1',      true],
            ['169.254.0.1',     true],
            ['0.0.0.1',         true],
            ['240.0.0.1',       true],
            ['::1',             true],
            ['fc00::1',         true],
            ['fe80::1',         true],
            ['::ffff:0:1',      true],
            ['fd00::1',         true],

            // public
            ['104.26.14.6',             false],
            ['2606:4700:20::681a:e06',  false],
        ];
    }

    public function testCacheSizeLimit()
    {
        $ref = new \ReflectionClass(IpUtils::class);

        /** @var array */
        $checkedIps = $ref->getStaticPropertyValue('checkedIps');
        $this->assertIsArray($checkedIps);

        $maxCheckedIps = 1000;

        for ($i = 1; $i < $maxCheckedIps * 1.5; ++$i) {
            $ip = '192.168.1.'.str_pad((string) $i, 3, '0');

            IpUtils::checkIp4($ip, '127.0.0.1');
        }

        $this->assertLessThan($maxCheckedIps, \count($checkedIps));
    }
}
