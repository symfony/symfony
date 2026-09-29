<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Response;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Internal\CurlClientState;
use Symfony\Component\HttpClient\Response\CurlResponse;

/**
 * @requires extension curl
 */
class CurlResponseTest extends TestCase
{
    /**
     * @dataProvider provideTimeoutsAboveWhatCurlAccepts
     */
    public function testSelectCapsTheTimeoutCurlAccepts(float $timeout)
    {
        $select = new \ReflectionMethod(CurlResponse::class, 'select');

        // curl takes the timeout as milliseconds in a signed 32-bit int, so anything
        // above 2147483.647 seconds is rejected: a warning before PHP 8.5, a
        // ValueError since. Either way the request that reached select() is lost.
        $this->assertSame(0, $select->invoke(null, new CurlClientState(0, 0), $timeout));
    }

    public static function provideTimeoutsAboveWhatCurlAccepts(): iterable
    {
        yield 'just above the limit' => [2147483.648];
        yield 'the value suggested for the cap' => [2147484.0];
        yield 'a max_duration of a year' => [31536000.0 * 100];
        yield 'within the limit is untouched' => [2147483.647];
    }
}
