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
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

/**
 * @group time-sensitive
 */
class PhpArrayAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testBasicUsage' => 'PhpArrayAdapter is read-only.',
        'testClear' => 'PhpArrayAdapter is read-only.',
        'testClearWithDeferredItems' => 'PhpArrayAdapter is read-only.',
        'testDeleteItem' => 'PhpArrayAdapter is read-only.',
        'testSaveExpired' => 'PhpArrayAdapter is read-only.',
        'testSaveWithoutExpire' => 'PhpArrayAdapter is read-only.',
        'testDeferredSave' => 'PhpArrayAdapter is read-only.',
        'testDeferredSaveWithoutCommit' => 'PhpArrayAdapter is read-only.',
        'testDeleteItems' => 'PhpArrayAdapter is read-only.',
        'testDeleteDeferredItem' => 'PhpArrayAdapter is read-only.',
        'testCommit' => 'PhpArrayAdapter is read-only.',
        'testSaveDeferredWhenChangingValues' => 'PhpArrayAdapter is read-only.',
        'testSaveDeferredOverwrite' => 'PhpArrayAdapter is read-only.',
        'testIsHitDeferred' => 'PhpArrayAdapter is read-only.',

        'testExpiresAt' => 'PhpArrayAdapter does not support expiration.',
        'testExpiresAtWithNull' => 'PhpArrayAdapter does not support expiration.',
        'testExpiresAfterWithNull' => 'PhpArrayAdapter does not support expiration.',
        'testDeferredExpired' => 'PhpArrayAdapter does not support expiration.',
        'testExpiration' => 'PhpArrayAdapter does not support expiration.',

        'testGetItemInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',
        'testGetItemsInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',
        'testHasItemInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',
        'testDeleteItemInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',
        'testDeleteItemsInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',

        'testDefaultLifeTime' => 'PhpArrayAdapter does not allow configuring a default lifetime.',
    );

    private static $file;

    public static function setupBeforeClass()
    {
        self::$file = sys_get_temp_dir().'/symfony-cache/php-array-adapter-test.php';
    }

    protected function tearDown()
    {
        if (file_exists(sys_get_temp_dir().'/symfony-cache')) {
            FilesystemAdapterTest::rmdir(sys_get_temp_dir().'/symfony-cache');
        }
    }

    public function createCachePool()
    {
        return new PhpArrayAdapterWrapper(self::$file, new NullAdapter());
    }

    public function testStore()
    {
        $arrayWithRefs = array();
        $arrayWithRefs[0] = 123;
        $arrayWithRefs[1] = &$arrayWithRefs[0];

        $object = (object) array(
            'foo' => 'bar',
            'foo2' => 'bar2',
        );

        $expected = array(
            'null' => null,
            'serializedString' => serialize($object),
            'arrayWithRefs' => $arrayWithRefs,
            'object' => $object,
            'arrayWithObject' => array('bar' => $object),
        );

        $adapter = $this->createCachePool();
        $adapter->warmUp($expected);

        foreach ($expected as $key => $value) {
            $this->assertSame(serialize($value), serialize($adapter->getItem($key)->get()), 'Warm up should create a PHP file that OPCache can load in memory');
        }
    }

    public function testStoredFile()
    {
        $expected = array(
            'integer' => 42,
            'float' => 42.42,
            'boolean' => true,
            'array_simple' => array('foo', 'bar'),
            'array_associative' => array('foo' => 'bar', 'foo2' => 'bar2'),
        );

        $adapter = $this->createCachePool();
        $adapter->warmUp($expected);

        $values = eval(substr(file_get_contents(self::$file), 6));

        $this->assertSame($expected, $values, 'Warm up should create a PHP file that OPCache can load in memory');
    }
}

class PhpArrayAdapterWrapper extends PhpArrayAdapter
{
    public function save(CacheItemInterface $item)
    {
        call_user_func(\Closure::bind(function () use ($item) {
            $this->values[$item->getKey()] = $item->get();
            $this->warmUp($this->values);
            $this->values = eval(substr(file_get_contents($this->file), 6));
        }, $this, PhpArrayAdapter::class));

        return true;
    }
}
