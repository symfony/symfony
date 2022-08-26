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

use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Eases with processing responses while streaming them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait AsyncDecoratorTrait
{
    use DecoratorTrait;

    /**
     * @return AsyncResponse
     */
    abstract public function request(string $method, string $url, array $options = []): ResponseInterface;

    public function stream(ResponseInterface|iterable $responses, float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof AsyncResponse) {
            $responses = [$responses];
        }

        return new ResponseStream(AsyncResponse::stream($responses, $timeout, static::class));
    }
}
