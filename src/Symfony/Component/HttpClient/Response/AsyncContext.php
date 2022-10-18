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

use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Chunk\LastChunk;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A DTO to work with AsyncResponse.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class AsyncContext
{
    private $passthru;
    private $client;
    private $response;
    private array $info = [];
    private $content;
    private int $offset;

    /**
     * @param resource|null $content
     */
    public function __construct(?callable &$passthru, HttpClientInterface $client, ResponseInterface &$response, array &$info, $content, int $offset)
    {
        $this->passthru = &$passthru;
        $this->client = $client;
        $this->response = &$response;
        $this->info = &$info;
        $this->content = $content;
        $this->offset = $offset;
    }

    /**
     * Returns the HTTP status without consuming the response.
     */
    public function getStatusCode(): int
    {
        return $this->response->getInfo('http_code');
    }

    /**
     * Returns the headers without consuming the response.
     */
    public function getHeaders(): array
    {
        $headers = [];

        foreach ($this->response->getInfo('response_headers') as $h) {
            if (11 <= \strlen($h) && '/' === $h[4] && preg_match('#^HTTP/\d+(?:\.\d+)? ([123456789]\d\d)(?: |$)#', $h, $m)) {
                $headers = [];
            } elseif (2 === \count($m = explode(':', $h, 2))) {
                $headers[strtolower($m[0])][] = ltrim($m[1]);
            }
        }

        return $headers;
    }

    /**
     * @return resource|null The PHP stream resource where the content is buffered, if it is
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Creates a new chunk of content.
     */
    public function createChunk(string $data): ChunkInterface
    {
        return new DataChunk($this->offset, $data);
    }

    /**
     * Pauses the request for the given number of seconds.
     */
    public function pause(float $duration): void
    {
        if (\is_callable($pause = $this->response->getInfo('pause_handler'))) {
            $pause($duration);
        } elseif (0 < $duration) {
            usleep(1E6 * $duration);
        }
    }

    /**
     * Cancels the request and returns the last chunk to yield.
     */
    public function cancel(): ChunkInterface
    {
        $this->info['canceled'] = true;
        $this->info['error'] = 'Response has been canceled.';
        $this->response->cancel();

        return new LastChunk();
    }

    /**
     * Returns the current info of the response.
     */
    public function getInfo(string $type = null): mixed
    {
        if (null !== $type) {
            return $this->info[$type] ?? $this->response->getInfo($type);
        }

        return $this->info + $this->response->getInfo();
    }

    /**
     * Attaches an info to the response.
     *
     * @return $this
     */
    public function setInfo(string $type, mixed $value): static
    {
        if ('canceled' === $type && $value !== $this->info['canceled']) {
            throw new \LogicException('You cannot set the "canceled" info directly.');
        }

        if (null === $value) {
            unset($this->info[$type]);
        } else {
            $this->info[$type] = $value;
        }

        return $this;
    }

    /**
     * Returns the currently processed response.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Replaces the currently processed response by doing a new request.
     */
    public function replaceRequest(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->info['previous_info'][] = $info = $this->response->getInfo();
        if (null !== $onProgress = $options['on_progress'] ?? null) {
            $thisInfo = &$this->info;
            $options['on_progress'] = static function (int $dlNow, int $dlSize, array $info) use (&$thisInfo, $onProgress) {
                $onProgress($dlNow, $dlSize, $thisInfo + $info);
            };
        }
        if (0 < ($info['max_duration'] ?? 0) && 0 < ($info['total_time'] ?? 0)) {
            if (0 >= $options['max_duration'] = $info['max_duration'] - $info['total_time']) {
                throw new TransportException(sprintf('Max duration was reached for "%s".', $info['url']));
            }
        }

        return $this->response = $this->client->request($method, $url, ['buffer' => false] + $options);
    }

    /**
     * Replaces the currently processed response by another one.
     */
    public function replaceResponse(ResponseInterface $response): ResponseInterface
    {
        $this->info['previous_info'][] = $this->response->getInfo();

        return $this->response = $response;
    }

    /**
     * Replaces or removes the chunk filter iterator.
     *
     * @param ?callable(ChunkInterface, self): ?\Iterator $passthru
     */
    public function passthru(callable $passthru = null): void
    {
        $this->passthru = $passthru ?? static function ($chunk, $context) {
            $context->passthru = null;

            yield $chunk;
        };
    }
}
