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

    public function __construct(Key $key)
    {
        $this->key = $key;
    }

    public function getKey(): Key
    {
        return $this->key;
    }
}
