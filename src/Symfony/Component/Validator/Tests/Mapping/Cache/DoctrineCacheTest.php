<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Cache;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Mapping\Cache\DoctrineCache;

/**
 * @group legacy
 */
class DoctrineCacheTest extends AbstractCacheTest
{
    protected function setUp(): void
    {
        $this->cache = class_exists(DoctrineProvider::class)
            ? new DoctrineCache(DoctrineProvider::wrap(new ArrayAdapter()))
            : new DoctrineCache(new ArrayCache())
        ;
    }
}
