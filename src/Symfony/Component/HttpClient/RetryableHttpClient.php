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
use Symfony\Component\HttpClient\Retry\RetryStrategyInterface;
use Symfony\Component\HttpClient\Retry\StatelessStrategy;
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

    private $strategy;
    private $maxRetries;
    private $logger;

    /**
     * @param int $maxRetries The maximum number of times to retry
     */
    public function __construct(HttpClientInterface $client, RetryStrategyInterface $strategy = null, int $maxRetries = 3, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->strategy = $strategy ?? new StatelessStrategy();
        $this->maxRetries = $maxRetries;
        $this->logger = $logger ?: new NullLogger();
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $retryToken = $this->strategy->getToken($method, $url, $options);

        if (null === $retryToken || $this->maxRetries <= 0) {
            return new AsyncResponse($this->client, $method, $url, $options);
        }

        $retryCount = 0;

        return new AsyncResponse($this->client, $method, $url, $options, function (ChunkInterface $chunk, AsyncContext $context) use ($method, $url, $options, $retryToken, &$retryCount, &$content, &$firstChunk) {
            $exception = null;
            try {
                if ($chunk->isTimeout() || null !== $chunk->getInformationalStatus()) {
                    yield $chunk;

                    return;
                }
            } catch (TransportExceptionInterface $exception) {
                // catch TransportExceptionInterface to send it to RetryToken::getDelay().
            }

            $statusCode = $context->getStatusCode();
            $headers = $context->getHeaders();
            if (null === $exception) {
                if ($chunk->isFirst()) {
                    $shouldRetry = $retryToken->shouldRetry($retryCount, $statusCode, $headers, null);

                    if (false === $shouldRetry) {
                        $context->passthru();
                        yield $chunk;

                        return;
                    }

                    // Body is needed to decide
                    if (null === $shouldRetry) {
                        $firstChunk = $chunk;
                        $content = '';

                        return;
                    }
                } else {
                    $content .= $chunk->getContent();

                    if (!$chunk->isLast()) {
                        return;
                    }

                    $shouldRetry = $retryToken->shouldRetry($retryCount, $statusCode, $headers, $content);

                    if (null === $shouldRetry) {
                        throw new \LogicException(sprintf('The "%s::shouldRetry()" method must not return null when called with a body.', get_debug_type($retryToken)));
                    }

                    if (false === $shouldRetry) {
                        $context->passthru();
                        yield $firstChunk;
                        yield $context->createChunk($content);
                        $content = '';

                        return;
                    }
                }
            }

            $context->setInfo('retry_count', $retryCount);
            $context->getResponse()->cancel();

            $delay = $this->getDelayFromHeader($headers) ?? $retryToken->getDelay($retryCount, $statusCode, $headers, $chunk instanceof LastChunk ? $content : null, $exception);
            ++$retryCount;

            $this->logger->info('Error returned by the server. Retrying #{retryCount} using {delay} ms delay: '.($exception ? $exception->getMessage() : 'StatusCode: '.$statusCode), [
                'retryCount' => $retryCount,
                'delay' => $delay,
            ]);

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
}
