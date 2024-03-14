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
    private bool $shouldBeReleasedBeforeHandlerCall;

    public function __construct(Key $key, bool $shouldBeReleasedBeforeHandlerCall)
    {
        $this->key = $key;
        $this->shouldBeReleasedBeforeHandlerCall = $shouldBeReleasedBeforeHandlerCall;
    }

    public function getKey(): Key
    {
        return $this->key;
    }

    public function shouldBeReleasedBeforeHandlerCall(): bool
    {
        return $this->shouldBeReleasedBeforeHandlerCall;
    }
}
