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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\VarDumper\Caster\ResourceCaster;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class ResourceCasterTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;
    use VarDumperTestTrait;

    /**
     * @group legacy
     *
     * @requires extension curl
     */
    public function testCastCurlIsDeprecated()
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);

        $this->expectUserDeprecationMessage('Since symfony/var-dumper 7.3: The "Symfony\Component\VarDumper\Caster\ResourceCaster::castCurl()" method is deprecated without replacement.');

        ResourceCaster::castCurl($ch, [], new Stub(), false);
    }

    /**
     * @group legacy
     *
     * @requires extension gd
     */
    public function testCastGdIsDeprecated()
    {
        $gd = imagecreate(1, 1);

        $this->expectUserDeprecationMessage('Since symfony/var-dumper 7.3: The "Symfony\Component\VarDumper\Caster\ResourceCaster::castGd()" method is deprecated without replacement.');

        ResourceCaster::castGd($gd, [], new Stub(), false);
    }

    /**
     * @requires PHP < 8.4
     * @requires extension dba
     */
    public function testCastDbaPriorToPhp84()
    {
        $dba = dba_open(sys_get_temp_dir().'/test.db', 'c');

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
dba resource {
  file: %s
}
EODUMP, $dba);
    }

    /**
     * @requires PHP 8.4.2
     * @requires extension dba
     */
    public function testCastDba()
    {
        $dba = dba_open(sys_get_temp_dir().'/test.db', 'c');

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
Dba\Connection {
  +file: %s
}
EODUMP, $dba);
    }

    /**
     * @requires PHP 8.4
     * @requires extension dba
     */
    public function testCastDbaOnBuggyPhp84()
    {
        if (\PHP_VERSION_ID >= 80402) {
            $this->markTestSkipped('The test can only be run on PHP 8.4.0 and 8.4.1, see https://github.com/php/php-src/issues/16990');
        }

        $dba = dba_open(sys_get_temp_dir().'/test.db', 'c');

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
Dba\Connection {
}
EODUMP, $dba);
    }
}
