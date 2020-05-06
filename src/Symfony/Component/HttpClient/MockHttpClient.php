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

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * A test-friendly HttpClient that doesn't make actual HTTP requests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class MockHttpClient implements HttpClientInterface
{
    use HttpClientTrait;

    private $responseFactory;
    private $baseUri;
    private $requestsCount = 0;

    /**
     * @param callable|callable[]|ResponseInterface|ResponseInterface[]|iterable|null $responseFactory
     */
    public function __construct($responseFactory = null, string $baseUri = null)
    {
        if ($responseFactory instanceof ResponseInterface) {
            $responseFactory = [$responseFactory];
        }

        if (!$responseFactory instanceof \Iterator && null !== $responseFactory && !\is_callable($responseFactory)) {
            $responseFactory = (static function () use ($responseFactory) {
                yield from $responseFactory;
            })();
        }

        $this->responseFactory = $responseFactory;
        $this->baseUri = $baseUri;
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        [$url, $options] = $this->prepareRequest($method, $url, $options, ['base_uri' => $this->baseUri], true);
        $url = implode('', $url);

        if (null === $this->responseFactory) {
            $response = new MockResponse();
        } elseif (\is_callable($this->responseFactory)) {
            $response = ($this->responseFactory)($method, $url, $options);
        } elseif (!$this->responseFactory->valid()) {
            throw new TransportException('The response factory iterator passed to MockHttpClient is empty.');
        } else {
            $responseFactory = $this->responseFactory->current();
            $response = \is_callable($responseFactory) ? $responseFactory($method, $url, $options) : $responseFactory;
            $this->responseFactory->next();
        }
        ++$this->requestsCount;

        return MockResponse::fromRequest($method, $url, $options, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        } elseif (!is_iterable($responses)) {
            throw new \TypeError(sprintf('"%s()" expects parameter 1 to be an iterable of MockResponse objects, "%s" given.', __METHOD__, get_debug_type($responses)));
        }

        return new ResponseStream(MockResponse::stream($responses, $timeout));
    }

    public function getRequestsCount(): int
    {
        return $this->requestsCount;
    }
}
