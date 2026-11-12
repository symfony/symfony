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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\Exception\InvalidIntervalException;
use Symfony\Component\RateLimiter\Policy\SlidingWindow;

class SlidingWindowTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

#[Group('time-sensitive')]
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
        $this->assertEquals(30, $window->calculateTimeForTokens(1, 4));
    }

    public function testCalculateTimeForTokensWaitsForTheEndOfTheWindowToReleaseItsHits()
    {
        ClockMock::register(SlidingWindow::class);
        $window = new SlidingWindow('foo', 20);
        $window->add(10);

        // the hits of the current window count in full until it ends, the first one fades out right after
        $this->assertEqualsWithDelta(20, $window->calculateTimeForTokens(10, 1), 0.01);
    }

    public function testCalculateTimeForTokensWhenThePreviousWindowReleasesEnoughHits()
    {
        ClockMock::register(SlidingWindow::class);
        $window = new SlidingWindow('foo', 20);
        $window->add(10);
        sleep(20);
        $window = SlidingWindow::createFromPreviousWindow($window, 20);
        sleep(10);
        $window->add(6);

        // 5 hits left from the previous window + 6: one must fade out, at 10 hits per 20 seconds
        $this->assertEqualsWithDelta(2, $window->calculateTimeForTokens(10, 1), 0.01);
    }

    public function testCalculateTimeForTokensWhenThePreviousWindowDoesNotReleaseEnoughHits()
    {
        ClockMock::register(SlidingWindow::class);
        $window = new SlidingWindow('foo', 20);
        $window->add(4);
        sleep(20);
        $window = SlidingWindow::createFromPreviousWindow($window, 20);
        sleep(10);
        $window->add(8);

        // 2 hits left from the previous window + 8: getting 3 tokens takes the end of the current window
        $this->assertEqualsWithDelta(10, $window->calculateTimeForTokens(10, 3), 0.01);
    }

    public function testGivenTimeIsUsedInsteadOfMicrotime()
    {
        $window = new SlidingWindow('foo', 10, 1000000000.0);

        $this->assertFalse($window->isExpired());
        $this->assertSame(20, $window->getExpirationTime());

        $window->configure(5, 1000000005.0);
        $this->assertFalse($window->isExpired());
        $this->assertSame(15, $window->getExpirationTime());

        $window->configure(5, 1000000010.5);
        $this->assertTrue($window->isExpired());
    }

    public function testCalculateTimeForTokensUsesTheGivenTime()
    {
        $window = new SlidingWindow('foo', 10, 1000000000.0);
        $window->configure(1, 1000000000.0);
        $window->add();

        $window->configure(1, 1000000004.0);
        $this->assertEqualsWithDelta(6, $window->calculateTimeForTokens(1, 1), 0.01);
    }

    public function testCreateFromPreviousWindowKeepsTheGivenTime()
    {
        $window = new SlidingWindow('foo', 10, 1000000000.0);
        $window->configure(10, 1000000000.0);
        $window->add(10);

        $window->configure(10, 1000000015.0);
        $this->assertTrue($window->isExpired());

        $new = SlidingWindow::createFromPreviousWindow($window, 10);

        $this->assertFalse($new->isExpired());
        // half of the previous window's hits have faded out
        $this->assertSame(5, $new->getHitCount());
    }

    public function testUnserializeRejectsObjectInTypedIdProperty()
    {
        $data = [
            'id' => new SlidingWindowTestToStringGadget(),
            'hitCount' => 0,
            'intervalInSeconds' => 1,
            'hitCountForLastWindow' => 0,
            'windowEndAt' => 0.0,
        ];
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(SlidingWindow::class), SlidingWindow::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }
        $payload .= '}';
        SlidingWindowTestToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(SlidingWindowTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }
}
