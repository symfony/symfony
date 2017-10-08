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
    /**
     * @dataProvider getIpv4Data
     */
    public function testIpv4($matches, $remoteAddr, $cidr)
    {
        $this->assertSame($matches, IpUtils::checkIp($remoteAddr, $cidr));
    }

    public function getIpv4Data()
    {
        return array(
            array(true, '192.168.1.1', '192.168.1.1'),
            array(true, '192.168.1.1', '192.168.1.1/1'),
            array(true, '192.168.1.1', '192.168.1.0/24'),
            array(false, '192.168.1.1', '1.2.3.4/1'),
            array(false, '192.168.1.1', '192.168.1.1/33'), // invalid subnet
            array(true, '192.168.1.1', array('1.2.3.4/1', '192.168.1.0/24')),
            array(true, '192.168.1.1', array('192.168.1.0/24', '1.2.3.4/1')),
            array(false, '192.168.1.1', array('1.2.3.4/1', '4.3.2.1/1')),
            array(true, '1.2.3.4', '0.0.0.0/0'),
            array(true, '1.2.3.4', '192.168.1.0/0'),
            array(false, '1.2.3.4', '256.256.256/0'), // invalid CIDR notation
            array(false, 'an_invalid_ip', '192.168.1.0/24'),
        );
    }

    /**
     * @dataProvider getIpv6Data
     */
    public function testIpv6($matches, $remoteAddr, $cidr)
    {
        if (!defined('AF_INET6')) {
            $this->markTestSkipped('Only works when PHP is compiled without the option "disable-ipv6".');
        }

        $this->assertSame($matches, IpUtils::checkIp($remoteAddr, $cidr));
    }

    public function getIpv6Data()
    {
        return array(
            array(true, '2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'),
            array(false, '2a00:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'),
            array(false, '2a01:198:603:0:396e:4789:8e99:890f', '::1'),
            array(true, '0:0:0:0:0:0:0:1', '::1'),
            array(false, '0:0:603:0:396e:4789:8e99:0001', '::1'),
            array(true, '2a01:198:603:0:396e:4789:8e99:890f', array('::1', '2a01:198:603:0::/65')),
            array(true, '2a01:198:603:0:396e:4789:8e99:890f', array('2a01:198:603:0::/65', '::1')),
            array(false, '2a01:198:603:0:396e:4789:8e99:890f', array('::1', '1a01:198:603:0::/65')),
            array(false, '}__test|O:21:&quot;JDatabaseDriverMysqli&quot;:3:{s:2', '::1'),
            array(false, '2a01:198:603:0:396e:4789:8e99:890f', 'unknown'),
        );
    }

    /**
     * @expectedException \RuntimeException
     * @requires extension sockets
     */
    public function testAnIpv6WithOptionDisabledIpv6()
    {
        if (defined('AF_INET6')) {
            $this->markTestSkipped('Only works when PHP is compiled with the option "disable-ipv6".');
        }

        IpUtils::checkIp('2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65');
    }
}
