<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Cache;

use Symfony\Contracts\Cache\CacheInterface;

class ServiceUsingCache
{
    private $cache;

    public function __construct(CacheInterface$myTaggablePool)
    {
        $this->cache = $myTaggablePool;
    }

    public function getCache(): CacheInterface
    {
        return $this->cache;
    }
}
