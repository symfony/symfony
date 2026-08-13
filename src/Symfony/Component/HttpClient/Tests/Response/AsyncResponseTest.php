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
use Symfony\Component\HttpClient\AsyncDecoratorTrait;
use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\AsyncContext;
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

    /**
     * When an error chunk has been yielded and the response is then dropped while a replacement
     * request is in flight, the replacement is abandoned before anything could check its status:
     * destructing it must not throw, the app already got the error.
     */
    public function testAbandonedReplacementResponseDoesNotThrowOnDestruct()
    {
        $client = new class(new MockHttpClient([new MockResponse('', ['http_code' => 302, 'redirect_url' => 'http://example.com/redirected']), new MockResponse('', ['http_code' => 301])])) implements HttpClientInterface {
            use AsyncDecoratorTrait;

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return new AsyncResponse($this->client, $method, $url, $options, static function (ChunkInterface $chunk, AsyncContext $context): \Generator {
                    if (null !== $chunk->getError() || !$chunk->isFirst()) {
                        yield $chunk;

                        return;
                    }

                    $context->replaceRequest('GET', $context->getInfo('redirect_url'), []);

                    yield new ErrorChunk($chunk->getOffset(), 'Simulated timeout');
                });
            }
        };

        $response = $client->request('GET', 'http://example.com/');

        foreach ($client->stream($response) as $chunk) {
            if ($chunk->isTimeout()) {
                break;
            }
        }

        unset($response);

        $this->addToAssertionCount(1);
    }
}
