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
use Symfony\Component\RateLimiter\Exception\InvalidIntervalException;
use Symfony\Component\RateLimiter\Policy\SlidingWindow;

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
}
