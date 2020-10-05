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
class StatelessStrategy implements RetryStrategyInterface
{
    private $decider;
    private $backoff;

    public function __construct(RetryDeciderInterface $decider = null, RetryBackOffInterface $backoff = null)
    {
        $this->decider = $decider ?? new HttpStatusCodeDecider();
        $this->backoff = $backoff ?? new ExponentialBackOff();
    }

    public function getToken(string $requestMethod, string $requestUrl, array $requestOptions): ?RetryToken
    {
        $decider = function ($retryCount, $responseStatusCode, $responseHeaders, $responseContent) use ($requestMethod, $requestUrl, $requestOptions) {
            return $this->decider->shouldRetry($retryCount, $requestMethod, $requestUrl, $requestOptions, $responseStatusCode, $responseHeaders, $responseContent);
        };

        $backoff = function ($retryCount, $responseStatusCode, $responseHeaders, $responseContent, $exception) use ($requestMethod, $requestUrl, $requestOptions) {
            return $this->backoff->getDelay($retryCount, $requestMethod, $requestUrl, $requestOptions, $responseStatusCode, $responseHeaders, $responseContent, $exception);
        };

        return new RetryToken($decider, $backoff);
    }
}
