<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use Symfony\Component\Cache\Simple\NullCache;
use Symfony\Component\Cache\Simple\PhpArrayCache;
use Symfony\Component\Cache\Tests\Adapter\FilesystemAdapterTest;

/**
 * @group time-sensitive
 * @group legacy
 */
class PhpArrayCacheTest extends CacheTestCase
{
    protected $skippedTests = [
        'testBasicUsageWithLongKey' => 'PhpArrayCache does no writes',

        'testDelete' => 'PhpArrayCache does no writes',
        'testDeleteMultiple' => 'PhpArrayCache does no writes',
        'testDeleteMultipleGenerator' => 'PhpArrayCache does no writes',

        'testSetTtl' => 'PhpArrayCache does no expiration',
        'testSetMultipleTtl' => 'PhpArrayCache does no expiration',
        'testSetExpiredTtl' => 'PhpArrayCache does no expiration',
        'testSetMultipleExpiredTtl' => 'PhpArrayCache does no expiration',

        'testGetInvalidKeys' => 'PhpArrayCache does no validation',
        'testGetMultipleInvalidKeys' => 'PhpArrayCache does no validation',
        'testSetInvalidKeys' => 'PhpArrayCache does no validation',
        'testDeleteInvalidKeys' => 'PhpArrayCache does no validation',
        'testDeleteMultipleInvalidKeys' => 'PhpArrayCache does no validation',
        'testSetInvalidTtl' => 'PhpArrayCache does no validation',
        'testSetMultipleInvalidKeys' => 'PhpArrayCache does no validation',
        'testSetMultipleInvalidTtl' => 'PhpArrayCache does no validation',
        'testHasInvalidKeys' => 'PhpArrayCache does no validation',
        'testSetValidData' => 'PhpArrayCache does no validation',

        'testDefaultLifeTime' => 'PhpArrayCache does not allow configuring a default lifetime.',
        'testPrune' => 'PhpArrayCache just proxies',
    ];

    protected static $file;

    public static function setUpBeforeClass(): void
    {
        self::$file = sys_get_temp_dir().'/symfony-cache/php-array-adapter-test.php';
    }

    protected function tearDown(): void
    {
        if (file_exists(sys_get_temp_dir().'/symfony-cache')) {
            FilesystemAdapterTest::rmdir(sys_get_temp_dir().'/symfony-cache');
        }
    }

    public function createSimpleCache()
    {
        return new PhpArrayCacheWrapper(self::$file, new NullCache());
    }

    public function testStore()
    {
        $arrayWithRefs = [];
        $arrayWithRefs[0] = 123;
        $arrayWithRefs[1] = &$arrayWithRefs[0];

        $object = (object) [
            'foo' => 'bar',
            'foo2' => 'bar2',
        ];

        $expected = [
            'null' => null,
            'serializedString' => serialize($object),
            'arrayWithRefs' => $arrayWithRefs,
            'object' => $object,
            'arrayWithObject' => ['bar' => $object],
        ];

        $cache = new PhpArrayCache(self::$file, new NullCache());
        $cache->warmUp($expected);

        foreach ($expected as $key => $value) {
            $this->assertSame(serialize($value), serialize($cache->get($key)), 'Warm up should create a PHP file that OPCache can load in memory');
        }
    }

    public function testStoredFile()
    {
        $data = [
            'integer' => 42,
            'float' => 42.42,
            'boolean' => true,
            'array_simple' => ['foo', 'bar'],
            'array_associative' => ['foo' => 'bar', 'foo2' => 'bar2'],
        ];
        $expected = [
            [
                'integer' => 0,
                'float' => 1,
                'boolean' => 2,
                'array_simple' => 3,
                'array_associative' => 4,
            ],
            [
                0 => 42,
                1 => 42.42,
                2 => true,
                3 => ['foo', 'bar'],
                4 => ['foo' => 'bar', 'foo2' => 'bar2'],
            ],
        ];

        $cache = new PhpArrayCache(self::$file, new NullCache());
        $cache->warmUp($data);

        $values = eval(substr(file_get_contents(self::$file), 6));

        $this->assertSame($expected, $values, 'Warm up should create a PHP file that OPCache can load in memory');
    }
}
