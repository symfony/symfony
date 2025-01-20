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
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires extension sockets
 */
class AddressInfoCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testCaster()
    {
        $xDump = <<<EODUMP
AddressInfo {
  ai_flags: 0
  ai_family: AF_INET%A
}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, socket_addrinfo_lookup('localhost')[0]);
    }
}
