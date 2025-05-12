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
     */
    public function testSignalExists()
    {
        $this->assertSame('SIGINT', SignalMap::getSignalName(\SIGINT));
        $this->assertSame('SIGKILL', SignalMap::getSignalName(\SIGKILL));
        $this->assertSame('SIGTERM', SignalMap::getSignalName(\SIGTERM));
        $this->assertSame('SIGSYS', SignalMap::getSignalName(\SIGSYS));
    }

    public function testSignalDoesNotExist()
    {
        $this->assertNull(SignalMap::getSignalName(999999));
    }
}
