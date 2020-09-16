<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @author Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class DelayStampTest extends TestCase
{
    public function testSeconds()
    {
        $stamp = DelayStamp::delayForSeconds(30);
        $this->assertSame(30000, $stamp->getDelay());
    }

    public function testMinutes()
    {
        $stamp = DelayStamp::delayForMinutes(30);
        $this->assertSame(1800000, $stamp->getDelay());
    }

    public function testHours()
    {
        $stamp = DelayStamp::delayForHours(30);
        $this->assertSame(108000000, $stamp->getDelay());
    }
}
