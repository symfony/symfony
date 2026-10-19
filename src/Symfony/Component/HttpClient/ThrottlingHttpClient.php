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

use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Limits the number of requests within a certain period.
 */
class ThrottlingHttpClient implements HttpClientInterface, ResetInterface
{
    use DecoratorTrait {
        reset as private traitReset;
    }

    public function __construct(
        HttpClientInterface $client,
        private readonly LimiterInterface $rateLimiter,
    ) {
        $this->client = $client;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $response = $this->client->request($method, $url, $options);

        if (0 < $waitDuration = $this->rateLimiter->reserve()->getWaitDuration()) {
            if (\is_callable($pause = $response->getInfo('pause_handler'))) {
                $pause($waitDuration);
            } else {
                usleep((int) (1E6 * $waitDuration));
            }
        }

        return $response;
    }

    /**
     * @param bool $shutdown Whether the client is being shut down
     */
    public function reset(/* bool $shutdown = false */): void
    {
        $this->traitReset(...\func_get_args());
        $this->rateLimiter->reset();
    }
}
