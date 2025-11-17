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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class ResourceCasterTest extends TestCase
{
    use VarDumperTestTrait;

    #[RequiresPhpExtension('dba')]
    public function testCastDba()
    {
        $dba = dba_open(sys_get_temp_dir().'/test.db', 'c');

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
                Dba\Connection {
                  +file: %s
                }
                EODUMP,
            $dba
        );
    }

    #[RequiresPhpExtension('dba')]
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
                EODUMP,
            $dba
        );
    }
}
