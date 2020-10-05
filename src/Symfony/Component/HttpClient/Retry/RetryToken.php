<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Retry;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RetryToken
{
    private $shouldRetry;
    private $getDelay;

    public function __construct(\Closure $shouldRetry, \Closure $getDelay)
    {
        $this->shouldRetry = $shouldRetry;
        $this->getDelay = $getDelay;
    }

    /**
     * Returns whether the request should be retried.
     *
     * @param ?string $responseContent Null is passed when the body did not arrive yet
     *
     * @return ?bool Returns null to signal that the body is required to take a decision
     */
    public function shouldRetry(int $retryCount, int $responseStatusCode, array $responseHeaders, ?string $responseContent): ?bool
    {
        return ($this->shouldRetry)($retryCount, $responseStatusCode, $responseHeaders, $responseContent);
    }

    /**
     * Returns the time to wait in milliseconds.
     */
    public function getDelay(int $retryCount, int $responseStatusCode, array $responseHeaders, ?string $responseContent, ?TransportExceptionInterface $exception): int
    {
        return ($this->getDelay)($retryCount, $responseStatusCode, $responseHeaders, $responseContent, $exception);
    }
}
