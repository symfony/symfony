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
    private $retryTimeout;
    private $logger;

    /**
     * @param int $maxRetries   The maximum number of times to retry
     * @param int $retryTimeout The idle timeout in seconds before retrying the request, defaults to the "default_socket_timeout" ini parameter.
     */
    public function __construct(HttpClientInterface $client, RetryDeciderInterface $decider = null, RetryBackOffInterface $strategy = null, int $maxRetries = 3, float $retryTimeout = null, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->decider = $decider ?? new HttpStatusCodeDecider();
        $this->strategy = $strategy ?? new ExponentialBackOff();
        $this->maxRetries = $maxRetries;
        $this->logger = $logger ?: new NullLogger();
        $this->retryTimeout = $retryTimeout ?? (float) ini_get('default_socket_timeout');
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->maxRetries <= 0) {
            return new AsyncResponse($this->client, $method, $url, $options);
        }

        $retryCount = 0;
        $content = '';
        $firstChunk = null;
        $lastChunk = \microtime(true);

        return new AsyncResponse($this->client, $method, $url, $options + ['timeout' => $this->retryTimeout], function (ChunkInterface $chunk, AsyncContext $context) use (&$lastChunk, $method, $url, $options, &$retryCount, &$content, &$firstChunk) {
            $exception = null;
            try {
                if ($chunk->isTimeout() && \microtime(true) - $lastChunk <= $this->retryTimeout) {
                    yield $chunk;

                    return;
                }
                $lastChunk = \microtime(true);
                if (null !== $chunk->getInformationalStatus()) {
                    yield $chunk;

                    return;
                }
            } catch (TransportExceptionInterface $exception) {
                // catch TransportExceptionInterface to send it to strategy.
            }

            $statusCode = $context->getStatusCode();
            $headers = $context->getHeaders();
            if (null === $exception) {
                if ($chunk->isFirst()) {
                    $shouldRetry = $this->decider->shouldRetry($method, $url, $options, $statusCode, $headers, null);

                    if (false === $shouldRetry) {
                        $context->passthru();
                        yield $chunk;

                        return;
                    }

                    // Decider need body to decide
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
                    $shouldRetry = $this->decider->shouldRetry($method, $url, $options, $statusCode, $headers, $content);
                    if (null === $shouldRetry) {
                        throw new \LogicException(sprintf('The "%s::shouldRetry" method must not return null when called with a body.', \get_class($this->decider)));
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

            $delay = $this->getDelayFromHeader($headers) ?? $this->strategy->getDelay($retryCount, $method, $url, $options, $statusCode, $headers, $chunk instanceof LastChunk ? $content : null, $exception);
            ++$retryCount;

            $this->logger->info('Error returned by the server. Retrying #{retryCount} using {delay} ms delay: '.($exception ? $exception->getMessage() : 'StatusCode: '.$statusCode), [
                'retryCount' => $retryCount,
                'delay' => $delay,
            ]);

            // it's expected to no having chunk in the next $delay seconds
            $lastChunk = \microtime(true) + $delay / 1000;
            if ($retryCount >= $this->maxRetries) {
                $context->replaceRequest($method, $url, $options);
                $context->pause($delay / 1000);

                $context->passthru();
            } else {
                $context->replaceRequest($method, $url, $options + ['timeout' => $this->retryTimeout + $delay / 1000]);
                $context->pause($delay / 1000);
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
