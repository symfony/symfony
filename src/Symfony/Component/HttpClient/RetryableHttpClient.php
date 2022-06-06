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
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\Retry\RetryStrategyInterface;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Automatically retries failing HTTP requests.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RetryableHttpClient implements HttpClientInterface, ResetInterface
{
    use AsyncDecoratorTrait;

    private $strategy;
    private $maxRetries;
    private $logger;

    /**
     * @param int $maxRetries The maximum number of times to retry
     */
    public function __construct(HttpClientInterface $client, RetryStrategyInterface $strategy = null, int $maxRetries = 3, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->strategy = $strategy ?? new GenericRetryStrategy();
        $this->maxRetries = $maxRetries;
        $this->logger = $logger ?? new NullLogger();
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->maxRetries <= 0) {
            return new AsyncResponse($this->client, $method, $url, $options);
        }

        $retryCount = 0;
        $content = '';
        $firstChunk = null;

        return new AsyncResponse($this->client, $method, $url, $options, function (ChunkInterface $chunk, AsyncContext $context) use ($method, $url, $options, &$retryCount, &$content, &$firstChunk) {
            $exception = null;
            try {
                if ($chunk->isTimeout() || null !== $chunk->getInformationalStatus() || $context->getInfo('canceled')) {
                    yield $chunk;

                    return;
                }
            } catch (TransportExceptionInterface $exception) {
                // catch TransportExceptionInterface to send it to the strategy
            }
            if (null !== $exception) {
                // always retry request that fail to resolve DNS
                if ('' !== $context->getInfo('primary_ip')) {
                    $shouldRetry = $this->strategy->shouldRetry($context, null, $exception);
                    if (null === $shouldRetry) {
                        throw new \LogicException(sprintf('The "%s::shouldRetry()" method must not return null when called with an exception.', \get_class($this->strategy)));
                    }

                    if (false === $shouldRetry) {
                        yield from $this->passthru($context, $firstChunk, $content, $chunk);

                        return;
                    }
                }
            } elseif ($chunk->isFirst()) {
                if (false === $shouldRetry = $this->strategy->shouldRetry($context, null, null)) {
                    yield from $this->passthru($context, $firstChunk, $content, $chunk);

                    return;
                }

                // Body is needed to decide
                if (null === $shouldRetry) {
                    $firstChunk = $chunk;
                    $content = '';

                    return;
                }
            } else {
                if (!$chunk->isLast()) {
                    $content .= $chunk->getContent();

                    return;
                }

                if (null === $shouldRetry = $this->strategy->shouldRetry($context, $content, null)) {
                    throw new \LogicException(sprintf('The "%s::shouldRetry()" method must not return null when called with a body.', \get_class($this->strategy)));
                }

                if (false === $shouldRetry) {
                    yield from $this->passthru($context, $firstChunk, $content, $chunk);

                    return;
                }
            }

            $context->getResponse()->cancel();

            $delay = $this->getDelayFromHeader($context->getHeaders()) ?? $this->strategy->getDelay($context, !$exception && $chunk->isLast() ? $content : null, $exception);
            ++$retryCount;

            $this->logger->info('Try #{count} after {delay}ms'.($exception ? ': '.$exception->getMessage() : ', status code: '.$context->getStatusCode()), [
                'count' => $retryCount,
                'delay' => $delay,
            ]);

            $context->setInfo('retry_count', $retryCount);
            $context->replaceRequest($method, $url, $options);
            $context->pause($delay / 1000);

            if ($retryCount >= $this->maxRetries) {
                $context->passthru();
            }
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

    private function passthru(AsyncContext $context, ?ChunkInterface $firstChunk, string &$content, ChunkInterface $lastChunk): \Generator
    {
        $context->passthru();

        if (null !== $firstChunk) {
            yield $firstChunk;
        }

        if ('' !== $content) {
            $chunk = $context->createChunk($content);
            $content = '';

            yield $chunk;
        }

        yield $lastChunk;
    }
}
