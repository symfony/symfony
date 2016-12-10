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

use Symfony\Component\Cache\Adapter\MemcacheAdapter;

abstract class AbstractMemcacheAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        //'testExpiration' => 'Testing expiration slows down the test suite',
        //'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
        //'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
    );

    /**
     * @var \Memcache|\Memcached
     */
    protected static $client;

    /**
     * "memcached" or "memcache".
     */
    protected static $extension;

    protected static function defaultConnectionServer()
    {
        return sprintf(
            '%s://%s:%d',
            static::$extension,
            getenv('MEMCACHED_HOST') ?: '127.0.0.1',
            getenv('MEMCACHED_PORT') ?: 11211
        );
    }

    public static function setupBeforeClass()
    {
        if (!extension_loaded(static::$extension)) {
            self::markTestSkipped(sprintf('Extension %s required.', static::$extension));
        }

        parent::setupBeforeClass();
    }

    public static function tearDownAfterClass()
    {
        self::$client->flush();
        self::$client = null;
    }

    /**
     * @group memcacheAdapter
     * @group memcacheAdapterWithValidDsn
     */
    public function provideValidServerConfigData()
    {
        $data = array(
            array(sprintf('%s:', static::$extension)),
            array(sprintf('%s:?weight=50', static::$extension)),
            array(sprintf('%s://127.0.0.1', static::$extension)),
            array(sprintf('%s://127.0.0.1:11211', static::$extension)),
            array(sprintf('%s://127.0.0.1?weight=50', static::$extension)),
            array(sprintf('%s://127.0.0.1:11211?weight=50', static::$extension)),
            array(sprintf('%s://127.0.0.1:11211?weight=50&extra-query=is-ignored', static::$extension)),
        );

        for ($i = 100; $i <= 65535; $i = $i + random_int(500, 1000)) {
            $data[] = array(sprintf('%s://127.0.0.1:%d', static::$extension, $i));
            $data[] = array(sprintf('%s://127.0.0.1:11211?weight=%d', static::$extension, max(floor(100 * $i / 65535), 1)));
        }

        return $data;
    }

    public function provideInvalidConnectionDsnSchema()
    {
        $data = array(
            array('http://google.com/?query=this+wont+work'),
            array('redis://secret@example.com/13'),
        );

        for ($i = 1; $i <= 10; ++$i) {
            $data[] = array(sprintf('http://%d.%d.%d.%d', random_int(255, 2550), random_int(255, 2550), random_int(255, 2550), random_int(255, 2550)));
            $data[] = array(sprintf('http://%d.%d.%d.%d', random_int(-2550, 0), random_int(-2550, 0), random_int(-2550, 0), random_int(-2550, -1)));
            $data[] = array(sprintf('%s://127.0.0.1', str_repeat(chr(random_int(97, 122)), random_int(1, 10))));
        }

        return $data;
    }

    public function provideInvalidConnectionDsnHostOrPort()
    {
        $data = array(
            array(sprintf('%s://invalid-host', static::$extension)),
            array(sprintf('%s://127.0.0.1:6553500', static::$extension)),
            array(sprintf('%s://127.0.0.1:-100', static::$extension)),
        );

        for ($i = 65536; $i < 65535 * 2; $i = $i + random_int(1000, 2000)) {
            $data[] = array(sprintf('%s://127.0.0.1:%d', static::$extension, $i));
        }

        return $data;
    }

    public function provideInvalidConnectionDsnQueryWeight()
    {
        $data = array(
            array(sprintf('%s://127.0.0.1?weight=200000', static::$extension)),
            array(sprintf('%s://127.0.0.1:11211?weight=foo-bar', static::$extension)),
            array(sprintf('%s://127.0.0.1?weight=0', static::$extension)),
            array(sprintf('%s://127.0.0.1:11211?weight=-100', static::$extension)),
        );

        for ($i = -101; $i > -1000; $i = $i - random_int(40, 60)) {
            $data[] = array(sprintf('%s://127.0.0.1?weight=%d', static::$extension, $i));
            $data[] = array(sprintf('%s://127.0.0.1?weight=%d', static::$extension, abs($i)));
        }

        return $data;
    }
}
