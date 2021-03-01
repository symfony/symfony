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
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\Exception\InvalidIntervalException;
use Symfony\Component\RateLimiter\Policy\SlidingWindow;

/**
 * @group time-sensitive
 */
class SlidingWindowTest extends TestCase
{
    public function testGetExpirationTime()
    {
        $window = new SlidingWindow('foo', 10);
        $this->assertSame(2 * 10, $window->getExpirationTime());
        $this->assertSame(2 * 10, $window->getExpirationTime());

        $data = serialize($window);
        $cachedWindow = unserialize($data);
        $this->assertNull($cachedWindow->getExpirationTime());

        $new = SlidingWindow::createFromPreviousWindow($cachedWindow, 15);
        $this->assertSame(2 * 15, $new->getExpirationTime());
    }

    public function testInvalidInterval()
    {
        $this->expectException(InvalidIntervalException::class);
        new SlidingWindow('foo', 0);
    }

    public function testLongInterval()
    {
        ClockMock::register(SlidingWindow::class);
        $window = new SlidingWindow('foo', 60);
        $this->assertSame(0, $window->getHitCount());
        $window->add(20);
        $this->assertSame(20, $window->getHitCount());

        sleep(60);
        $new = SlidingWindow::createFromPreviousWindow($window, 60);
        $this->assertSame(20, $new->getHitCount());

        sleep(30);
        $this->assertSame(10, $new->getHitCount());

        sleep(30);
        $this->assertSame(0, $new->getHitCount());

        sleep(30);
        $this->assertSame(0, $new->getHitCount());
    }

    public function testLongIntervalCreate()
    {
        ClockMock::register(SlidingWindow::class);
        $window = new SlidingWindow('foo', 60);

        sleep(300);
        $new = SlidingWindow::createFromPreviousWindow($window, 60);
        $this->assertFalse($new->isExpired());
    }
}
