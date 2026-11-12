<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Policy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\Policy\CalendarAlignedWindow;

class CalendarAlignedWindowTest extends TestCase
{
    public function testExpirationTimeIsRelativeToTheConfiguredTime()
    {
        $start = new \DateTimeImmutable('2001-09-09 00:00:00', new \DateTimeZone('UTC'));
        $interval = new \DateInterval('P1D');
        $window = new CalendarAlignedWindow('foo', 2, $start, $start->add($interval), $interval);

        $window->configure($interval, 2, $start, (float) $start->getTimestamp() + 3600);

        $this->assertSame(86400 - 3600, $window->getExpirationTime());
    }

    public function testExpirationTimeCoversTheReservedPeriods()
    {
        $start = new \DateTimeImmutable('2001-09-09 00:00:00', new \DateTimeZone('UTC'));
        $interval = new \DateInterval('P1D');
        $window = new CalendarAlignedWindow('foo', 2, $start, $start->add($interval), $interval);

        $window->configure($interval, 2, $start, (float) $start->getTimestamp() + 3600);
        $window->add(3);

        $this->assertSame(2 * 86400 - 3600, $window->getExpirationTime());
    }
}
