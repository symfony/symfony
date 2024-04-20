<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\SignalRegistry;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\SignalRegistry\SignalMap;

class SignalMapTest extends TestCase
{
    /**
     * @requires extension pcntl
     *
     * @testWith [2, "SIGINT"]
     *           [9, "SIGKILL"]
     *           [15, "SIGTERM"]
     */
    public function testSignalExists(int $signal, string $expected)
    {
        $this->assertSame($expected, SignalMap::getSignalName($signal));
    }

    public function testSignalDoesNotExist()
    {
        $this->assertNull(SignalMap::getSignalName(999999));
    }
}
