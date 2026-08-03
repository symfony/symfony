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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
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

    #[DataProvider('getIpv4Data')]
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

    #[DataProvider('getIpv6Data')]
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

    #[RequiresPhpExtension('sockets')]
    public function testAnIpv6WithOptionDisabledIpv6()
    {
        $this->expectException(\RuntimeException::class);
        if (\defined('AF_INET6')) {
            $this->markTestSkipped('Only works when PHP is compiled with the option "disable-ipv6".');
        }

        IpUtils::checkIp('2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65');
    }

    #[DataProvider('invalidIpAddressData')]
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

    #[DataProvider('anonymizedIpData')]
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

    #[DataProvider('anonymizedIpDataWithBytes')]
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

    #[DataProvider('getIp4SubnetMaskZeroData')]
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

    #[DataProvider('getIsPrivateIpData')]
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
            ['192.0.0.1',       true],   // IETF Protocol Assignments (RFC 6890)
            ['192.0.2.1',       true],
            ['198.51.100.1',    true],
            ['203.0.113.1',     true],
            ['172.16.0.1',      true],
            ['169.254.0.1',     true],
            ['192.88.99.1',     true],   // 6to4 Relay Anycast (RFC 7526)
            ['198.18.0.1',      true],
            ['0.0.0.1',         true],
            ['240.0.0.1',       true],
            ['100.64.0.1',      true],
            ['224.0.0.1',       true],   // IPv4 multicast (RFC 5771)
            ['239.255.255.250', true],   // SSDP / mDNS / LLMNR range
            ['::1',             true],
            ['fc00::1',         true],
            ['fe80::1',         true],
            ['::ffff:0:1',      true],
            ['fd00::1',         true],
            ['::7f00:1',           true],
            ['2002:7f00:1::',      true],
            ['2001::1',            true],
            ['2001:db8::1',        true],
            ['2001:0002::1',       true],
            ['64:ff9b::7f00:1',    true],
            ['64:ff9b:1::7f00:1',  true],
            ['100::1',         true],   // Discard prefix (RFC 6666)
            ['ff02::1',        true],   // IPv6 multicast (RFC 4291)
            ['ff05::1',        true],   // site-local multicast

            // public
            ['104.26.14.6',             false],
            ['2606:4700:20::681a:e06',  false],
        ];
    }

    #[DataProvider('getIsPrivateIpInvalidData')]
    public function testIsPrivateIpThrowsOnNonCanonicalIp(string $ip)
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage(\sprintf('"%s" is not a valid IP address.', $ip));

        IpUtils::isPrivateIp($ip);
    }

    public static function getIsPrivateIpInvalidData(): array
    {
        return [
            'decimal' => ['2130706433'],
            'hexadecimal' => ['0x7f000001'],
            'leading zero' => ['010.0.0.1'],
            'short form' => ['127.1'],
            'zone id' => ['fe80::1%eth0'],
            'not an IP' => ['not-an-ip'],
            'empty string' => [''],
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

    #[DataProvider('getNormalizeData')]
    public function testNormalize(string $input, ?string $expected)
    {
        $this->assertSame($expected, IpUtils::normalize($input));
    }

    public static function getNormalizeData(): array
    {
        return [
            // canonical IPv4 passes through
            'canonical ipv4' => ['127.0.0.1',         '127.0.0.1'],
            'canonical ipv4 public' => ['1.1.1.1',           '1.1.1.1'],
            'zero ipv4' => ['0.0.0.0',           '0.0.0.0'],

            // canonical IPv6 passes through
            'canonical ipv6 loopback' => ['::1',               '::1'],
            'canonical ipv6 public' => ['2606:4700::1111',   '2606:4700::1111'],

            // IPv4-mapped IPv6 collapses to the embedded IPv4
            'ipv4-mapped dotted' => ['::ffff:127.0.0.1',  '127.0.0.1'],
            'ipv4-mapped hex' => ['::ffff:7f00:1',     '127.0.0.1'],

            // bracketed IPv6 (e.g. from URL host parsing)
            'bracketed ipv6' => ['[::1]',             '::1'],
            'bracketed ipv4-mapped' => ['[::ffff:127.0.0.1]', '127.0.0.1'],

            // numeric integer forms (common SSRF bypass)
            'decimal int' => ['2130706433',        '127.0.0.1'],
            'hex int' => ['0x7f000001',        '127.0.0.1'],
            'octal int' => ['017700000001',      '127.0.0.1'],

            // short dotted form
            'short form 127.1' => ['127.1',             '127.0.0.1'],
            'short form 192.168.1' => ['192.168.1',         '192.168.0.1'],
            'short form 10.0.1' => ['10.0.1',            '10.0.0.1'],

            // mixed dotted with octal/hex components
            'mixed hex/dot' => ['0x7f.0.0.1',        '127.0.0.1'],
            'mixed octal/dot' => ['0177.0.0.01',       '127.0.0.1'],
            'mixed hex mid' => ['127.0x0.0.1',       '127.0.0.1'],
            'octal-leading' => ['0177.0.0.1',        '127.0.0.1'],

            // hex-leading dotted (each component hex)
            'all hex dotted' => ['0x7f.0x0.0x0.0x1',  '127.0.0.1'],

            // IPv6 with zone identifier is normalised without the zone
            'ipv6 with zone' => ['fe80::1%eth0',      'fe80::1'],

            // out of range / malformed inputs return null
            'octet out of range' => ['256.0.0.0',         null],
            'all octets out of range' => ['256.256.256.256',   null],
            'too many octets' => ['127.0.0.1.1',       null],
            'integer out of range' => ['4294967296',        null], // > 0xFFFFFFFF

            // non-IP inputs return null
            'hostname' => ['example.com',       null],
            'garbage' => ['not-an-ip',         null],
            'empty string' => ['',                  null],
            'mixed with letters' => ['127.foo.0.1',       null],
        ];
    }

    public function testNormalizeClassifiesObfuscatedPrivateIps()
    {
        // Common SSRF bypasses: every form below is accepted by cURL
        // as 127.0.0.1 at the network layer, but the unnormalised
        // forms fail filter_var(FILTER_VALIDATE_IP). Normalising them
        // makes them route through isPrivateIp() correctly.
        $bypasses = [
            '2130706433',
            '0x7f000001',
            '017700000001',
            '127.1',
            '0x7f.0.0.1',
            '0177.0.0.01',
        ];

        foreach ($bypasses as $bypass) {
            $canonical = IpUtils::normalize($bypass);
            $this->assertNotNull($canonical, "normalize() should accept {$bypass}");
            $this->assertTrue(
                IpUtils::isPrivateIp($canonical),
                "Normalised form of {$bypass} should be classified as private."
            );
        }
    }

    public function testNormalizeAcceptsObfuscatedPublicIps()
    {
        // The same normalisation that prevents SSRF bypass must not
        // break legitimate use of obfuscated public addresses. Each
        // input below is a well-known public IP rendered in a form
        // filter_var(FILTER_VALIDATE_IP) rejects; after normalisation
        // it must round-trip to the canonical form and be classified
        // as public.
        //
        // The shape mirrors testNormalizeClassifiesObfuscatedPrivateIps
        // — one test for the bypass-blocking direction, one for the
        // legitimate-use direction.
        $publicAddresses = [
            // 1.1.1.1 — Cloudflare DNS
            ['1.1.1.1',         '1.1.1.1'],
            ['16843009',        '1.1.1.1'],   // decimal of 1.1.1.1
            ['0x01010101',      '1.1.1.1'],   // hex of 1.1.1.1
            ['0x1.0x1.0x1.0x1', '1.1.1.1'],   // all-hex dotted
            // 8.8.8.8 — Google DNS
            ['8.8.8.8',         '8.8.8.8'],
            ['134744072',       '8.8.8.8'],   // decimal of 8.8.8.8
            // 9.9.9.9 — Quad9 DNS
            ['9.9.9.9',         '9.9.9.9'],
            // 104.26.14.6 — public IP explicitly listed in getIsPrivateIpData
            ['104.26.14.6',     '104.26.14.6'],
            // Public IPv6 addresses
            ['2606:4700:20::681a:e06',  '2606:4700:20::681a:e06'],
            ['2001:4860:4860::8888',    '2001:4860:4860::8888'],
            // IPv4-mapped IPv6 wrapping a public IPv4
            ['::ffff:1.1.1.1',  '1.1.1.1'],
            ['::ffff:8.8.8.8',  '8.8.8.8'],
        ];

        foreach ($publicAddresses as [$input, $expectedCanonical]) {
            $canonical = IpUtils::normalize($input);
            $this->assertSame(
                $expectedCanonical,
                $canonical,
                "normalize() should canonicalise {$input} to {$expectedCanonical}"
            );
            $this->assertFalse(
                IpUtils::isPrivateIp($canonical),
                "Normalised form of {$input} ({$canonical}) should be classified as public."
            );
        }
    }
}
