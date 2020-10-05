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
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface RetryDeciderInterface
{
    /**
     * Returns whether the request should be retried.
     *
     * @param ?string $responseContent Null is passed when the body did not arrive yet
     *
     * @return ?bool Returns null to signal that the body is required to take a decision
     */
    public function shouldRetry(string $requestMethod, string $requestUrl, array $requestOptions, int $responseStatusCode, array $responseHeaders, ?string $responseContent): ?bool;
}
