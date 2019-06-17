<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;

trait ExpiringStoreTrait
{
    private function checkNotExpired(Key $key)
    {
        if ($key->isExpired()) {
            try {
                $this->delete($key);
            } catch (\Exception $e) {
                // swallow exception to not hide the original issue
            }
            throw new LockExpiredException(sprintf('Failed to store the "%s" lock.', $key));
        }
    }
}
