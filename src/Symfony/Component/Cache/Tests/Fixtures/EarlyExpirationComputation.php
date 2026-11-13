<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Fixtures;

use Symfony\Component\Cache\CacheItem;

class EarlyExpirationComputation
{
    public int $calls = 0;
    public bool $save = true;

    public function __construct(private array $values = [1, 2])
    {
    }

    public function __invoke(CacheItem $item, bool &$save): mixed
    {
        $item->expiresAfter(3600);
        // Ensure a non-zero computation time for probabilistic early expiration.
        usleep(1000);
        $save = $this->save;
        $value = $this->values[$this->calls++];

        if ($value instanceof \Throwable) {
            throw $value;
        }

        return $value;
    }
}
