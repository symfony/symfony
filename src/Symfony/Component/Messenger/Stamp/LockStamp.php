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
    private ?float $ttl;
    private bool $shouldBeReleasedBeforeHandlerCall;

    public function __construct(
        string $key,
        ?float $ttl = 300.0,
        bool $shouldBeReleasedBeforeHandlerCall = false,
    ) {
        $this->key = new Key($key);
        $this->ttl = $ttl;
        $this->shouldBeReleasedBeforeHandlerCall = $shouldBeReleasedBeforeHandlerCall;
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
