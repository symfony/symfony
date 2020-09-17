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

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface RetryBackOffInterface
{
    /**
     * Returns the time to wait in milliseconds.
     */
    public function getDelay(int $retryCount, string $requestMethod, string $requestUrl, array $requestOptions, ResponseInterface $partialResponse, \Throwable $throwable = null): int;
}
