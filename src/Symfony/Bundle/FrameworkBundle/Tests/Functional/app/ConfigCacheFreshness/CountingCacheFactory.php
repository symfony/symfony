<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness;

use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;

class CountingCacheFactory implements ConfigCacheFactoryInterface
{
    public function __construct(private ConfigCacheFactoryInterface $inner, private string $directory)
    {
    }

    /**
     * Records each successful cache rebuild outside the tracked input directories.
     */
    public function cache(string $file, callable $callable): ConfigCacheInterface
    {
        return $this->inner->cache($file, function (ConfigCacheInterface $cache) use ($file, $callable): void {
            $callable($cache);
            file_put_contents($this->directory.'/dumps', $file."\n", \FILE_APPEND);
        });
    }
}
