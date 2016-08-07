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

use Symfony\Component\Cache\Adapter\ApcuAdapter;

class ApcuAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testExpiration' => 'Testing expiration slows down the test suite',
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
        'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
    );

    public function createCachePool($defaultLifetime = 0)
    {
        if (!function_exists('apcu_fetch') || !ini_get('apc.enabled') || ('cli' === PHP_SAPI && !ini_get('apc.enable_cli'))) {
            $this->markTestSkipped('APCu extension is required.');
        }
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Fails transiently on Windows.');
        }

        return new ApcuAdapter(str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    public function testUnserializable()
    {
        $pool = $this->createCachePool();

        $item = $pool->getItem('foo');
        $item->set(function () {});

        $this->assertFalse($pool->save($item));

        $item = $pool->getItem('foo');
        $this->assertFalse($item->isHit());
    }

    public function testVersion()
    {
        $namespace = str_replace('\\', '.', __CLASS__);

        $pool1 = new ApcuAdapter($namespace, 0, 'p1');

        $item = $pool1->getItem('foo');
        $this->assertFalse($item->isHit());
        $this->assertTrue($pool1->save($item->set('bar')));

        $item = $pool1->getItem('foo');
        $this->assertTrue($item->isHit());
        $this->assertSame('bar', $item->get());

        $pool2 = new ApcuAdapter($namespace, 0, 'p2');

        $item = $pool2->getItem('foo');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());

        $item = $pool1->getItem('foo');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());
    }
}
