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
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * Implements common logic for response classes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait CommonResponseTrait
{
    /**
     * @var callable|null A callback that tells whether we're waiting for response headers
     */
    private $initializer;
    private $shouldBuffer;
    private $content;
    private int $offset = 0;
    private ?array $jsonData = null;

    public function getContent(bool $throw = true): string
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        if ($throw) {
            $this->checkStatusCode();
        }

        if (null === $this->content) {
            $content = null;

            foreach (self::stream([$this]) as $chunk) {
                if (!$chunk->isLast()) {
                    $content .= $chunk->getContent();
                }
            }

            if (null !== $content) {
                return $content;
            }

            if (null === $this->content) {
                throw new TransportException('Cannot get the content of the response twice: buffering is disabled.');
            }
        } else {
            foreach (self::stream([$this]) as $chunk) {
                // Chunks are buffered in $this->content already
            }
        }

        rewind($this->content);

        return stream_get_contents($this->content);
    }

    public function toArray(bool $throw = true): array
    {
        if ('' === $content = $this->getContent($throw)) {
            throw new JsonException('Response body is empty.');
        }

        if (null !== $this->jsonData) {
            return $this->jsonData;
        }

        try {
            $content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonException($e->getMessage().sprintf(' for "%s".', $this->getInfo('url')), $e->getCode());
        }

        if (!\is_array($content)) {
            throw new JsonException(sprintf('JSON content was expected to decode to an array, "%s" returned for "%s".', get_debug_type($content), $this->getInfo('url')));
        }

        if (null !== $this->content) {
            // Option "buffer" is true
            return $this->jsonData = $content;
        }

        return $content;
    }

    public function toStream(bool $throw = true)
    {
        if ($throw) {
            // Ensure headers arrived
            $this->getHeaders($throw);
        }

        $stream = StreamWrapper::createResource($this);
        stream_get_meta_data($stream)['wrapper_data']
            ->bindHandles($this->handle, $this->content);

        return $stream;
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    /**
     * Closes the response and all its network handles.
     */
    abstract protected function close(): void;

    private static function initialize(self $response): void
    {
        if (null !== $response->getInfo('error')) {
            throw new TransportException($response->getInfo('error'));
        }

        try {
            if (($response->initializer)($response, -0.0)) {
                foreach (self::stream([$response], -0.0) as $chunk) {
                    if ($chunk->isFirst()) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Persist timeouts thrown during initialization
            $response->info['error'] = $e->getMessage();
            $response->close();
            throw $e;
        }

        $response->initializer = null;
    }

    private function checkStatusCode()
    {
        $code = $this->getInfo('http_code');

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
