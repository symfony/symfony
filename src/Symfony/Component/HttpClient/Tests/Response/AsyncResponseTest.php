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
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AsyncResponseTest extends TestCase
{
    public function testReplacingTheResponseByAConsumedOneRearmsTheConsumptionGuard()
    {
        $client2 = new MockHttpClient([new MockResponse('second body')]);

        $passthru = static function (ChunkInterface $chunk, $context) use ($client2): \Generator {
            if (null !== $chunk->getError() || !$chunk->isFirst()) {
                yield $chunk;

                return;
            }

            $response = $client2->request('GET', 'http://example.com/second');
            $response->getStatusCode();
            $context->replaceResponse($response);
        };

        $response = new AsyncResponse(new MockHttpClient([new MockResponse('first body')]), 'GET', 'http://example.com/', [], $passthru);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is already consumed and cannot be managed by');

        $response->getContent();
    }

    public function testStreamingAConsumedResponseWithoutPassthruHitsTheConsumptionGuard()
    {
        $decorated = new class(new MockHttpClient([new MockResponse('body')])) implements HttpClientInterface {
            use DecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                $response = $this->client->request($method, $url, $options);
                $response->getStatusCode();

                return $response;
            }
        };

        $response = new AsyncResponse($decorated, 'GET', 'http://example.com/', []);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is already consumed and cannot be managed by');

        $response->getContent();
    }

    public function testGettingTheContentTwiceIsNotMistakenForOutOfBandConsumption()
    {
        $passthru = static function (ChunkInterface $chunk, $context): \Generator {
            yield $chunk;
        };

        $response = new AsyncResponse(new MockHttpClient([new MockResponse('body')]), 'GET', 'http://example.com/', [], $passthru);

        $this->assertSame('body', $response->getContent());
        $this->assertSame('body', $response->getContent());
    }
}
