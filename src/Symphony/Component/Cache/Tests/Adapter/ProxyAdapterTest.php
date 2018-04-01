<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests\Adapter;

use Psr\Cache\CacheItemInterface;
use Symphony\Component\Cache\Adapter\ArrayAdapter;
use Symphony\Component\Cache\Adapter\ProxyAdapter;
use Symphony\Component\Cache\CacheItem;

/**
 * @group time-sensitive
 */
class ProxyAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testPrune' => 'ProxyAdapter just proxies',
    );

    public function createCachePool($defaultLifetime = 0)
    {
        return new ProxyAdapter(new ArrayAdapter(), '', $defaultLifetime);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage OK bar
     */
    public function testProxyfiedItem()
    {
        $item = new CacheItem();
        $pool = new ProxyAdapter(new TestingArrayAdapter($item));

        $proxyItem = $pool->getItem('foo');

        $this->assertNotSame($item, $proxyItem);
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
