<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Tests;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Decorates "http_client.transport" with the default decoration priority and records the requests it sees.
 */
class TransportDecorator implements HttpClientInterface
{
    /** @var list<string> */
    public static array $requests = [];

    public function __construct(
        private HttpClientInterface $inner,
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        self::$requests[] = $method.' '.$url.' '.implode(',', array_filter((array) ($options['headers'] ?? []), static fn ($h) => str_starts_with((string) $h, 'X-Scoped')));

        return $this->inner->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->inner->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withOptions($options);

        return $clone;
    }
}
