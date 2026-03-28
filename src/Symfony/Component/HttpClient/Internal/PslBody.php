<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Psl\Async\CancellationTokenInterface;
use Psl\Async\NullCancellationToken;
use Psl\IO;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * Request body wrapper that tracks upload progress for PSL HTTP client.
 *
 * @author Seifeddine Gmati <azjezz@carthage.software>
 *
 * @internal
 */
class PslBody implements IO\ReadHandleInterface
{
    use IO\ReadHandleConvenienceMethodsTrait;

    private IO\ReadHandleInterface $body;
    private ?string $content;
    private array $info;
    private ?int $offset = 0;
    private int $length = -1;
    private ?int $uploaded = null;

    /**
     * @param \Closure|IO\ReadHandleInterface|resource|string $body
     */
    public function __construct(
        $body,
        array &$info,
        private \Closure $onProgress,
    ) {
        $this->info = &$info;

        if ($body instanceof IO\ReadHandleInterface) {
            if ($body instanceof IO\SeekHandleInterface) {
                $this->offset = $body->tell();
            }

            $this->body = $body;
        } elseif (\is_resource($body)) {
            $this->offset = ftell($body);
            $this->length = fstat($body)['size'];
            $this->body = new IO\SeekReadStreamHandle($body);
        } elseif (\is_string($body)) {
            $this->content = $body;
            $this->body = new IO\MemoryHandle($body);
        } else {
            $this->body = new IO\IterableReadHandle((static function () use ($body) {
                while ('' !== $data = ($body)(16372)) {
                    if (!\is_string($data)) {
                        throw new TransportException(\sprintf('Return value of the "body" option callback must be string, "%s" returned.', get_debug_type($data)));
                    }

                    yield $data;
                }
            })());
        }
    }

    public function read(
        ?int $maxBytes = null,
        CancellationTokenInterface $cancellation = new NullCancellationToken(),
    ): string {
        $this->info['size_upload'] += $this->uploaded;
        $this->uploaded = 0;
        ($this->onProgress)();

        $data = $this->body->read($maxBytes, $cancellation);
        $this->uploaded = \strlen($data);
        if ($this->reachedEndOfDataSource()) {
            $this->info['upload_content_length'] = $this->info['size_upload'];
        }

        return $data;
    }

    public function tryRead(?int $maxBytes = null): string
    {
        $data = $this->body->tryRead($maxBytes);
        if ('' !== $data) {
            $this->uploaded += \strlen($data);
        }

        return $data;
    }

    public function reachedEndOfDataSource(): bool
    {
        return $this->body->reachedEndOfDataSource();
    }

    public function rewind(): void
    {
        $this->uploaded = null;
        if (null !== $this->content) {
            $this->body = new IO\MemoryHandle($this->content);

            return;
        }

        if (null !== $this->offset && $this->body instanceof IO\SeekHandleInterface) {
            $this->body->seek($this->offset);
        }
    }
}
