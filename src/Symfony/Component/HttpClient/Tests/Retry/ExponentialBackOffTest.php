<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Retry;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Retry\ExponentialBackOff;

class ExponentialBackOffTest extends TestCase
{
    /**
     * @dataProvider provideDelay
     */
    public function testGetDelay(int $delay, int $multiplier, int $maxDelay, int $previousRetries, int $expectedDelay)
    {
        $backOff = new ExponentialBackOff($delay, $multiplier, $maxDelay);

        self::assertSame($expectedDelay, $backOff->getDelay($previousRetries, 'GET', 'http://example.com/', [], 200, [], null, null));
    }

    public function provideDelay(): iterable
    {
        // delay, multiplier, maxDelay, retries, expectedDelay
        yield [1000, 1, 5000, 0, 1000];
        yield [1000, 1, 5000, 1, 1000];
        yield [1000, 1, 5000, 2, 1000];

        yield [1000, 2, 10000, 0, 1000];
        yield [1000, 2, 10000, 1, 2000];
        yield [1000, 2, 10000, 2, 4000];
        yield [1000, 2, 10000, 3, 8000];
        yield [1000, 2, 10000, 4, 10000]; // max hit
        yield [1000, 2, 0, 4, 16000]; // no max

        yield [1000, 3, 10000, 0, 1000];
        yield [1000, 3, 10000, 1, 3000];
        yield [1000, 3, 10000, 2, 9000];

        yield [1000, 1, 500, 0, 500]; // max hit immediately

        // never a delay
        yield [0, 2, 10000, 0, 0];
        yield [0, 2, 10000, 1, 0];
    }
}
