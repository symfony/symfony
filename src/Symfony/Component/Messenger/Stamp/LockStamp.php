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
    private Key $key;

    public function __construct(
        string $key,
        private ?float $ttl = 300.0,
        private bool $shouldBeReleasedBeforeHandlerCall = false,
    ) {
        $this->key = new Key($key);
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
