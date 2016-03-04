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

use Cache\IntegrationTests\CachePoolTest;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;

/**
 * @group time-sensitive
 */
class ProxyAdapterTest extends CachePoolTest
{
    protected $skippedTests = array(
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which ArrayAdapter is not.',
    );

    public function createCachePool()
    {
        return new ProxyAdapter(new ArrayAdapter());
    }

    public function testGetHitsMisses()
    {
        $pool = $this->createCachePool();

        $this->assertSame(0, $pool->getHits());
        $this->assertSame(0, $pool->getMisses());

        $bar = $pool->getItem('bar');
        $this->assertSame(0, $pool->getHits());
        $this->assertSame(1, $pool->getMisses());

        $pool->save($bar->set('baz'));
        $bar = $pool->getItem('bar');
        $this->assertSame(1, $pool->getHits());
        $this->assertSame(1, $pool->getMisses());
    }
}
