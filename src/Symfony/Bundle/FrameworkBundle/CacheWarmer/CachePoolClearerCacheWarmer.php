<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Clears the cache pools when warming up the cache.
 *
 * Do not use in production!
 *
 * @author Teoh Han Hui <teohhanhui@gmail.com>
 *
 * @internal
 */
final class CachePoolClearerCacheWarmer implements CacheWarmerInterface
{
    private $poolClearer;
    private $pools;

    public function __construct(Psr6CacheClearer $poolClearer, array $pools = [])
    {
        $this->poolClearer = $poolClearer;
        $this->pools = $pools;
    }

    /**
     * {@inheritdoc}
     *
     * @return string[]
     */
    public function warmUp($cacheDirectory): array
    {
        foreach ($this->pools as $pool) {
            if ($this->poolClearer->hasPool($pool)) {
                $this->poolClearer->clearPool($pool);
            }
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional(): bool
    {
        // optional cache warmers are not run when handling the request
        return false;
    }
}
