<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Response;

use GuzzleHttp\Promise\PromiseInterface as GuzzlePromiseInterface;
use Http\Promise\Promise as HttplugPromiseInterface;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @internal
 */
final class HttplugPromise implements HttplugPromiseInterface
{
    private $promise;

    public function __construct(GuzzlePromiseInterface $promise)
    {
        $this->promise = $promise;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null): self
    {
        return new self($this->promise->then($onFulfilled, $onRejected));
    }

    public function cancel(): void
    {
        $this->promise->cancel();
    }

    /**
     * {@inheritdoc}
     */
    public function getState(): string
    {
        return $this->promise->getState();
    }

    /**
     * {@inheritdoc}
     *
     * @return Psr7ResponseInterface|mixed
     */
    public function wait($unwrap = true)
    {
        $result = $this->promise->wait($unwrap);

        while ($result instanceof HttplugPromiseInterface || $result instanceof GuzzlePromiseInterface) {
            $result = $result->wait($unwrap);
        }

        return $result;
    }
}
