<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Policy;

trait ResetLimiterTrait
{
    private $lock;
    private $storage;
    private string $id;

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        try {
            $this->lock->acquire(true);

            $this->storage->delete($this->id);
        } finally {
            $this->lock->release();
        }
    }
}
