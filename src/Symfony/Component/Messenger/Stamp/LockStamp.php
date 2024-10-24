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
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

final class LockStamp implements StampInterface
{
    public const MODE_DISCARD = 0;
    public const MODE_BLOCK = 1;

    private Key $key;

    public function __construct(
        private int $mode,
        string $key,
        private ?float $ttl = 300.0,
        private bool $shouldBeReleasedBeforeHandlerCall = false,
    ) {
        if (!\in_array($this->mode, [self::MODE_DISCARD, self::MODE_BLOCK])) {
            throw new InvalidArgumentException(\sprintf('Supported modes are "%s".', implode('", "', [self::MODE_DISCARD, self::MODE_BLOCK])));
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

    public function getKey(): Key
    {
        return $this->key;
    }

    public function getTtl(): ?float
    {
        return $this->ttl;
    }

    public function shouldBeReleasedBeforeHandlerCall(): bool
    {
        return $this->shouldBeReleasedBeforeHandlerCall;
    }
}
