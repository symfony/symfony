<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;

/**
 * @group time-sensitive
 */
class NamespacedProxyAdapterTest extends ProxyAdapterTest
{
    public function createCachePool($defaultLifetime = 0)
    {
        return new ProxyAdapter(new ArrayAdapter(), 'foo', $defaultLifetime);
    }
}
