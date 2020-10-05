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
 * Decides to retry the request when HTTP status codes belong to the given list of codes.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class HttpStatusCodeDecider implements RetryDeciderInterface
{
    private $statusCodes;

    /**
     * @param array $statusCodes List of HTTP status codes that trigger a retry
     */
    public function __construct(array $statusCodes = [423, 425, 429, 500, 502, 503, 504, 507, 510])
    {
        $this->statusCodes = $statusCodes;
    }

    public function shouldRetry(string $requestMethod, string $requestUrl, array $requestOptions, int $responseStatusCode, array $responseHeaders, ?string $responseContent): ?bool
    {
        return \in_array($responseStatusCode, $this->statusCodes, true);
    }
}
