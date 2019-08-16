<?php


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
