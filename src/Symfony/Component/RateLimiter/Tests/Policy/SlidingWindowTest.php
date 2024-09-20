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
        sleep(10);
        $cachedWindow = unserialize($data);
        $this->assertSame(10, $cachedWindow->getExpirationTime());

        $new = SlidingWindow::createFromPreviousWindow($cachedWindow, 15);
        $this->assertSame(2 * 15, $new->getExpirationTime());

        usleep(10.1);
        $this->assertIsInt($new->getExpirationTime());
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

    public function testCreateFromPreviousWindowUsesMicrotime()
    {
        ClockMock::register(SlidingWindow::class);
        $window = new SlidingWindow('foo', 8);
        $window->add();

        usleep(11.6 * 1e6); // wait just under 12s (8+4)
        $new = SlidingWindow::createFromPreviousWindow($window, 4);
        $new->add();

        // should be 400ms left (12 - 11.6)
        $this->assertEqualsWithDelta(0.4, $new->calculateTimeForTokens(1, 1), 0.1);
    }

    public function testIsExpiredUsesMicrotime()
    {
        ClockMock::register(SlidingWindow::class);
        $window = new SlidingWindow('foo', 10);

        usleep(10.1 * 1e6);
        $this->assertTrue($window->isExpired());
    }

    public function testGetRetryAfterUsesMicrotime()
    {
        $window = new SlidingWindow('foo', 10);
        $window->add();

        usleep(9.5 * 1e6);
        // should be 500ms left (10 - 9.5)
        $this->assertEqualsWithDelta(0.5, $window->calculateTimeForTokens(1, 1), 0.1);
    }

    public function testCreateAtExactTime()
    {
        $window = new SlidingWindow('foo', 10);
        $this->assertEquals(30, $window->calculateTimeForTokens(1, 4));

        $window = new SlidingWindow('foo', 10);
        $window->add();
        $window = SlidingWindow::createFromPreviousWindow($window, 10);
        sleep(10);
        $this->assertEquals(40, $window->calculateTimeForTokens(1, 4));
    }
}
