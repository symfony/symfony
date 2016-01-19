<?php

namespace Symfony\Component\Validator\Tests\Mapping\Cache;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Mapping\Cache\Psr6Cache;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Psr6CacheTest extends AbstractCacheTest
{
    protected function setUp()
    {
        $this->cache = new Psr6Cache(new ArrayAdapter());
    }
}
