<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Lock\Key;

final class LockStamp implements StampInterface
{
    public const MODE_DISCARD_DUPLICATE_IN_QUEUE = 0;
    public const MODE_DISCARD_DUPLICATE_IN_QUEUE_OR_PROCESS = 1;
    public const MODE_BLOCK_DUPLICATE_IN_PROCESS = 2;

    private const MODES = [
        self::MODE_DISCARD_DUPLICATE_IN_QUEUE,
        self::MODE_DISCARD_DUPLICATE_IN_QUEUE_OR_PROCESS,
        self::MODE_BLOCK_DUPLICATE_IN_PROCESS,
    ];

    private Key $key;

    /**
     * @param self::MODE_* $mode
     */
    public function __construct(
        private int $mode,
        string $key,
        private ?float $ttl = 300.0,
    ) {
        if (!\in_array($this->mode, self::MODES)) {
            throw new InvalidArgumentException(\sprintf('Supported modes are "%s".', implode('", "', self::MODES)));
        }

        $this->key = new Key($key);
    }

    /**
     * @return self::MODE_*
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    public function shouldDiscardDuplicate(): bool
    {
        return in_array($this->mode, [
            self::MODE_DISCARD_DUPLICATE_IN_QUEUE,
            self::MODE_DISCARD_DUPLICATE_IN_QUEUE_OR_PROCESS,
        ]);
    }

    public function shouldBlockDuplicateInProcess(): bool
    {
        return $this->mode === self::MODE_BLOCK_DUPLICATE_IN_PROCESS;
    }

    public function shouldBeReleasedBeforeHandlerCall(): bool
    {
        return $this->mode === self::MODE_DISCARD_DUPLICATE_IN_QUEUE;
    }

    public function getKey(): Key
    {
        return $this->key;
    }

    public function getTtl(): ?float
    {
        return $this->ttl;
    }
}
