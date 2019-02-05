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

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class ApiResponse implements ResponseInterface
{
    private $response;
    private $data;
    private $error;
    private $timeout;

    /**
     * @internal
     */
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        $this->clearError();

        return $this->response->getStatusCode();
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(bool $throw = true): array
    {
        $this->clearError();

        return $this->response->getHeaders($throw);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(bool $throw = true): string
    {
        $this->clearError();

        return $this->response->getContent($throw);
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(string $type = null)
    {
        return $this->response->getInfo($type);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $throw = true): array
    {
        $this->clearError();

        return $this->response->toArray($throw);
    }

    public function __destruct()
    {
        if ($this->timeout) {
            throw new TransportException('API response reached the inactivity timeout.');
        }

        $this->clearError();
    }

    private function clearError()
    {
        $e = $this->error;
        $this->timeout = $this->error = null;

        if ($e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param self[] $apiResponses
     *
     * @internal
     */
    public static function complete(HttpClientInterface $client, iterable $apiResponses, ?float $timeout): \Generator
    {
        $responses = new \SplObjectStorage();

        foreach ($apiResponses as $k => $r) {
            if (!$r instanceof self) {
                throw new \TypeError(sprintf('ApiClient::complete() expects parameter $responses to be iterable of ApiResponse objects, %s given.', __METHOD__, \is_object($r) ? \get_class($r) : \gettype($r)));
            }

            $responses[$r->response] = [$k, $r];
        }
        $apiResponses = null;

        foreach ($client->stream($responses, $timeout) as $response => $chunk) {
            [$k, $r] = $responses[$response];

            try {
                // Skip timed out responses but throw on destruct if unchecked
                $r->timeout = $chunk->isTimeout();

                if ($r->timeout || !$chunk->isLast()) {
                    continue;
                }
            } catch (TransportExceptionInterface $e) {
                // Ensure errors are always thrown, last resort on destruct
                $r->error = $e;
                // Ensure PHP can clear memory as early as possible
                $e = null;
            }

            unset($responses[$response]);

            yield $k => $r;

            $r->clearError();
        }
    }
}
