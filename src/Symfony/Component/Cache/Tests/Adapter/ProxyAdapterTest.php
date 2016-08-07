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

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * @group time-sensitive
 */
class ProxyAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which ArrayAdapter is not.',
    );

    public function createCachePool($defaultLifetime = 0)
    {
        return new ProxyAdapter(new ArrayAdapter(), '', $defaultLifetime);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage OK bar
     */
    public function testProxyfiedItem()
    {
        $item = new CacheItem();
        $pool = new ProxyAdapter(new TestingArrayAdapter($item));

        $proxyItem = $pool->getItem('foo');

        $this->assertFalse($proxyItem === $item);
        $pool->save($proxyItem->set('bar'));
    }
}

class TestingArrayAdapter extends ArrayAdapter
{
    private $item;

    public function __construct(CacheItemInterface $item)
    {
        $this->item = $item;
    }

    public function getItem($key)
    {
        return $this->item;
    }

    public function save(CacheItemInterface $item)
    {
        if ($item === $this->item) {
            throw new \Exception('OK '.$item->get());
        }
    }
}
