<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\CacheDriver;

use Symfony\Component\Cache\Driver\MemcachedDriver;

class MemcachedCacheDriverTest extends AbstractCacheDriverTest
{
    /**
     * @var \Memcached
     */
    private $memcached = null;


    public function setUp()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('Please install MemcacheD extension to execute this test');
        }

        if (null === $this->memcached) {
            $fh = @fsockopen('127.0.0.1', 11211);
            if (!$fh) {
                $this->markTestSkipped('Memcached server is not on standard 127.0.0.1:11211 configuration');
            }

            $this->memcached = new \Memcached();
            $this->memcached->addServer('127.0.0.1', 11211);
        }

        parent::setUp();
    }

    public function _getTestDriver()
    {
        return new MemcachedDriver($this->memcached);
    }
}