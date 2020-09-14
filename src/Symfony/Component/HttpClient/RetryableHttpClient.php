<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\ExponentialBackOff;
use Symfony\Component\HttpClient\Retry\HttpStatusCodeDecider;
use Symfony\Component\HttpClient\Retry\RetryBackOffInterface;
use Symfony\Component\HttpClient\Retry\RetryDeciderInterface;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Automatically retries failing HTTP requests.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RetryableHttpClient implements HttpClientInterface
{
    use AsyncDecoratorTrait;

    private $decider;
    private $strategy;
    private $maxRetries;
    private $logger;

    /**
     * @param int $maxRetries The maximum number of times to retry
     */
    public function __construct(HttpClientInterface $client, RetryDeciderInterface $decider = null, RetryBackOffInterface $strategy = null, int $maxRetries = 3, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->decider = $decider ?? new HttpStatusCodeDecider();
        $this->strategy = $strategy ?? new ExponentialBackOff();
        $this->maxRetries = $maxRetries;
        $this->logger = $logger ?: new NullLogger();
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $retryCount = 0;

        return new AsyncResponse($this->client, $method, $url, $options, function (ChunkInterface $chunk, AsyncContext $context) use ($method, $url, $options, &$retryCount) {
            $exception = null;
            try {
                if ($chunk->isTimeout() || null !== $chunk->getInformationalStatus()) {
                    yield $chunk;

                    return;
                }

                // only retry first chunk
                if (!$chunk->isFirst()) {
                    $context->passthru();
                    yield $chunk;

                    return;
                }
            } catch (TransportExceptionInterface $exception) {
                // catch TransportExceptionInterface to send it to strategy.
            }

            $statusCode = $context->getStatusCode();
            $headers = $context->getHeaders();
            if ($retryCount >= $this->maxRetries || !$this->decider->shouldRetry($method, $url, $options, $partialResponse = new MockResponse($context->getContent(), ['http_code' => $statusCode, 'headers' => $headers]), $exception)) {
                $context->passthru();
                yield $chunk;

                return;
            }

            $context->setInfo('retry_count', $retryCount);
            $context->getResponse()->cancel();

            $delay = $this->getDelayFromHeader($headers) ?? $this->strategy->getDelay($retryCount, $method, $url, $options, $partialResponse, $exception);
            ++$retryCount;

            $this->logger->info('Error returned by the server. Retrying #{retryCount} using {delay} ms delay: '.($exception ? $exception->getMessage() : 'StatusCode: '.$statusCode), [
                'retryCount' => $retryCount,
                'delay' => $delay,
            ]);

            $context->replaceRequest($method, $url, $options);
            $context->pause($delay / 1000);
        });
    }

    private function getDelayFromHeader(array $headers): ?int
    {
        if (null !== $after = $headers['retry-after'][0] ?? null) {
            if (is_numeric($after)) {
                return (int) $after * 1000;
            }
            if (false !== $time = strtotime($after)) {
                return max(0, $time - time()) * 1000;
            }
        }

        return null;
    }
}
