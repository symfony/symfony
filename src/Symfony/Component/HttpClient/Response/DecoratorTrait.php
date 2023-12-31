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

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Cyril Vermande <https://github.com/cyve>
 */
trait DecoratorTrait
{
    private ResponseInterface $response;

    public function __construct(
        ResponseInterface $response,
    ) {
        $this->response = $response;
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
        return $this->response->getContent($throw);
    }

    public function toArray(bool $throw = true): array
    {
        return $this->response->toArray($throw);
    }

    public function cancel(): void
    {
        $this->response->cancel();
    }

    public function getInfo(string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    /**
     * @return resource
     */
    public function toStream(bool $throw = true)
    {
        if ($throw) {
            // Ensure headers arrived
            $this->response->getHeaders();
        }

        if ($this->response instanceof StreamableInterface) {
            return $this->response->toStream($throw);
        }

        return StreamWrapper::createResource($this->response);
    }

    /**
     * @internal
     */
    public static function stream(HttpClientInterface $client, ResponseInterface|iterable $responses, float $timeout = null): \Generator
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        $wrappedResponses = [];
        $responseMap = new \SplObjectStorage();

        foreach ($responses as $response) {
            $responseMap[$response->response] = $response;
            $wrappedResponses[] = $response->response;
        }

        foreach ($client->stream($wrappedResponses, $timeout) as $wrappedResponse => $chunk) {
            yield $responseMap[$wrappedResponse] => $chunk;
        }
    }
}
