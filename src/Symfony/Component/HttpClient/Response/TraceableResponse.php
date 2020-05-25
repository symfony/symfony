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

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class TraceableResponse implements ResponseInterface
{
    private $client;
    private $response;
    private $content;

    public function __construct(HttpClientInterface $client, ResponseInterface $response, &$content)
    {
        $this->client = $client;
        $this->response = $response;
        $this->content = &$content;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->response->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        $this->content = $this->response->getContent(false);

        if ($throw) {
            $this->checkStatusCode($this->response->getStatusCode());
        }

        return $this->content;
    }

    public function toArray(bool $throw = true): array
    {
        $this->content = $this->response->toArray(false);

        if ($throw) {
            $this->checkStatusCode($this->response->getStatusCode());
        }

        return $this->content;
    }

    public function cancel(): void
    {
        $this->response->cancel();
    }

    public function getInfo(string $type = null)
    {
        return $this->response->getInfo($type);
    }

    /**
     * Casts the response to a PHP stream resource.
     *
     * @return resource
     *
     * @throws TransportExceptionInterface   When a network error occurs
     * @throws RedirectionExceptionInterface On a 3xx when $throw is true and the "max_redirects" option has been reached
     * @throws ClientExceptionInterface      On a 4xx when $throw is true
     * @throws ServerExceptionInterface      On a 5xx when $throw is true
     */
    public function toStream(bool $throw = true)
    {
        if ($throw) {
            // Ensure headers arrived
            $this->response->getHeaders(true);
        }

        if (\is_callable([$this->response, 'toStream'])) {
            return $this->response->toStream(false);
        }

        return StreamWrapper::createResource($this->response, $this->client);
    }

    /**
     * @internal
     */
    public static function stream(HttpClientInterface $client, iterable $responses, ?float $timeout): \Generator
    {
        $wrappedResponses = [];
        $traceableMap = new \SplObjectStorage();

        foreach ($responses as $r) {
            if (!$r instanceof self) {
                throw new \TypeError(sprintf('"%s::stream()" expects parameter 1 to be an iterable of TraceableResponse objects, "%s" given.', TraceableHttpClient::class, get_debug_type($r)));
            }

            $traceableMap[$r->response] = $r;
            $wrappedResponses[] = $r->response;
        }

        foreach ($client->stream($wrappedResponses, $timeout) as $r => $chunk) {
            yield $traceableMap[$r] => $chunk;
        }
    }

    private function checkStatusCode($code)
    {
        if (500 <= $code) {
            throw new ServerException($this);
        }

        if (400 <= $code) {
            throw new ClientException($this);
        }

        if (300 <= $code) {
            throw new RedirectionException($this);
        }
    }
}
