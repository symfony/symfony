<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ThrottlingHttpClient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class ThrottlingHttpClientTest extends TestCase
{
    public function testThrottling()
    {
        $failPauseHandler = static function (float $duration) {
            self::fail(sprintf('The pause handler should\'t have been called, but it was called with %f.', $duration));
        };

        $pauseHandler = static fn (float $expectedDuration) => function (float $duration) use ($expectedDuration) {
            self::assertEqualsWithDelta($expectedDuration, $duration, 1);
        };

        $rateLimiterFactory = new RateLimiterFactory([
            'id' => 'token_bucket',
            'policy' => 'token_bucket',
            'limit' => 2,
            'rate' => ['interval' => '5 seconds', 'amount' => 2],
        ], new InMemoryStorage());

        $client = new ThrottlingHttpClient(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 200, 'pause_handler' => $failPauseHandler]),
                new MockResponse('', ['http_code' => 200, 'pause_handler' => $failPauseHandler]),
                new MockResponse('', ['http_code' => 200, 'pause_handler' => $pauseHandler(5)]),
                new MockResponse('', ['http_code' => 200, 'pause_handler' => $pauseHandler(5)]),
                new MockResponse('', ['http_code' => 200, 'pause_handler' => $pauseHandler(10)]),
            ]),
            $rateLimiterFactory->create(),
        );

        $client->request('GET', 'http://example.com/foo');
        $client->request('GET', 'http://example.com/bar');
        $client->request('GET', 'http://example.com/baz');
        $client->request('GET', 'http://example.com/qux');
        $client->request('GET', 'http://example.com/corge');
    }
}
