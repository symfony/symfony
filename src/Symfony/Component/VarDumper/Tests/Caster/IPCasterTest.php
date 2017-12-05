<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use Darsyn\IP\IP;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Caster\IPCaster;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Patrick Landolt <patrick.landolt@artack.ch>
 */
class IPCasterTest extends TestCase
{
    use VarDumperTestTrait;

    /**
     * @dataProvider provideIPs
     */
    public function testDumpIP($ip, $xVersion, $xShort, $xLong, $xMapped, $xDerived, $xCompatible, $xEmbedded, $xLinkLocal, $xLoopback, $xMulticast, $xPrivateUse, $xUnspecified)
    {
        $ip = new IP($ip);

        $xDump = <<<EODUMP
Darsyn\IP\IP {
  version: $xVersion
  short: "$xShort"
  long: "$xLong"
  mapped: $xMapped
  derived: $xDerived
  compatible: $xCompatible
  embedded: $xEmbedded
  link local: $xLinkLocal
  loopback: $xLoopback
  multicast: $xMulticast
  private use: $xPrivateUse
  unspecified: $xUnspecified
}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $ip);
    }

    /**
     * @dataProvider provideIPs
     */
    public function testCastIP($ip, $xVersion, $xShort, $xLong, $xMapped, $xDerived, $xCompatible, $xEmbedded, $xLinkLocal, $xLoopback, $xMulticast, $xPrivateUse, $xUnspecified)
    {
        $ip = new IP($ip);
        $cast = IPCaster::castIP($ip, array('foo' => 'bar'), new Stub(), false);

        $xDump = <<<EODUMP
array:12 [
  "\\x00~\\x00version" => $xVersion
  "\\x00~\\x00short" => "$xShort"
  "\\x00~\\x00long" => "$xLong"
  "\\x00~\\x00mapped" => $xMapped
  "\\x00~\\x00derived" => $xDerived
  "\\x00~\\x00compatible" => $xCompatible
  "\\x00~\\x00embedded" => $xEmbedded
  "\\x00~\\x00link local" => $xLinkLocal
  "\\x00~\\x00loopback" => $xLoopback
  "\\x00~\\x00multicast" => $xMulticast
  "\\x00~\\x00private use" => $xPrivateUse
  "\\x00~\\x00unspecified" => $xUnspecified
]
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $cast);
    }

    public function provideIPs()
    {
        return array(
            // array('127.0.0.1', xVersion, 'xShort', 'xLong', 'xMapped', 'xDerived', 'xCompatible', 'xEmbedded', 'xLinkLocal', 'xPrivateUse', 'xUnspecified'),

            // Mapped
            array('::ffff:222.1.41.90', 4, '::ffff:222.1.41.90', '0000:0000:0000:0000:0000:ffff:de01:295a', 'true', 'false', 'false', 'true', 'false', 'false', 'false', 'false', 'false'),

            // Derived
            array('2002:7f00:1::', 6, '2002:7f00:1::', '2002:7f00:0001:0000:0000:0000:0000:0000', 'false', 'true', 'false', 'false', 'false', 'false', 'false', 'false', 'false'),

            // Compatible
            array('::7f00:1', 4, '127.0.0.1', '0000:0000:0000:0000:0000:0000:7f00:0001', 'false', 'false', 'true', 'true', 'false', 'true', 'false', 'false', 'false'),

            // Embedded
            array('::ffff:7f00:1', 4, '::ffff:127.0.0.1', '0000:0000:0000:0000:0000:ffff:7f00:0001', 'true', 'false', 'false', 'true', 'false', 'false', 'false', 'false', 'false'),

            // Link Local
            array('169.254.0.1', 4, '169.254.0.1', '0000:0000:0000:0000:0000:0000:a9fe:0001', 'false', 'false', 'true', 'true', 'true', 'false', 'false', 'false', 'false'),
            array('fe80:0000:0000:0000:0000:0000:0000:0001', 6, 'fe80::1', 'fe80:0000:0000:0000:0000:0000:0000:0001', 'false', 'false', 'false', 'false', 'true', 'false', 'false', 'false', 'false'),

            // Loopback
            array('127.0.0.1', 4, '127.0.0.1', '0000:0000:0000:0000:0000:0000:7f00:0001', 'false', 'false', 'true', 'true', 'false', 'true', 'false', 'false', 'false'),
            array('0000:0000:0000:0000:0000:0000:0000:0001', 4, '0.0.0.1', '0000:0000:0000:0000:0000:0000:0000:0001', 'false', 'false', 'true', 'true', 'false', 'true', 'false', 'false', 'false'),

            // Multicast
            array('234.0.0.1', 4, '234.0.0.1', '0000:0000:0000:0000:0000:0000:ea00:0001', 'false', 'false', 'true', 'true', 'false', 'false', 'true', 'false', 'false'),
            array('ff01:0000:0000:0000:0000:0000:0000:0101', 6, 'ff01::101', 'ff01:0000:0000:0000:0000:0000:0000:0101', 'false', 'false', 'false', 'false', 'false', 'false', 'true', 'false', 'false'),

            // Private use
            array('10.0.0.1', 4, '10.0.0.1', '0000:0000:0000:0000:0000:0000:0a00:0001', 'false', 'false', 'true', 'true', 'false', 'false', 'false', 'true', 'false'),
            array('fdde:6bfa:68af:da88:0000:0000:0000:0001', 6, 'fdde:6bfa:68af:da88::1', 'fdde:6bfa:68af:da88:0000:0000:0000:0001', 'false', 'false', 'false', 'false', 'false', 'false', 'false', 'true', 'false'),

            // Unspecified
            array('0.0.0.0', 4, '0.0.0.0', '0000:0000:0000:0000:0000:0000:0000:0000', 'false', 'false', 'true', 'true', 'false', 'false', 'false', 'false', 'true'),
            array('0000:0000:0000:0000:0000:0000:0000:0000', 4, '0.0.0.0', '0000:0000:0000:0000:0000:0000:0000:0000', 'false', 'false', 'true', 'true', 'false', 'false', 'false', 'false', 'true'),

            // Mixed
            array('2002:7f00:1::', 6, '2002:7f00:1::', '2002:7f00:0001:0000:0000:0000:0000:0000', 'false', 'true', 'false', 'false', 'false', 'false', 'false', 'false', 'false'),
            array('::7f00:1', 4, '127.0.0.1', '0000:0000:0000:0000:0000:0000:7f00:0001', 'false', 'false', 'true', 'true', 'false', 'true', 'false', 'false', 'false'),
            array('127.0.0.1', 4, '127.0.0.1', '0000:0000:0000:0000:0000:0000:7f00:0001', 'false', 'false', 'true', 'true', 'false', 'true', 'false', 'false', 'false'),
            array('169.254.0.10', 4, '169.254.0.10', '0000:0000:0000:0000:0000:0000:a9fe:000a', 'false', 'false', 'true', 'true', 'true', 'false', 'false', 'false', 'false'),
            array('172.16.39.10', 4, '172.16.39.10', '0000:0000:0000:0000:0000:0000:ac10:270a', 'false', 'false', 'true', 'true', 'false', 'false', 'false', 'true', 'false'),
            array('8.8.8.8', 4, '8.8.8.8', '0000:0000:0000:0000:0000:0000:0808:0808', 'false', 'false', 'true', 'true', 'false', 'false', 'false', 'false', 'false'),
            array('2001:0db8:85a3:08d3:1319:8a2e:0370:7347', 6, '2001:db8:85a3:8d3:1319:8a2e:370:7347', '2001:0db8:85a3:08d3:1319:8a2e:0370:7347', 'false', 'false', 'false', 'false', 'false', 'false', 'false', 'false', 'false'),
        );
    }
}
