<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests\Simple;

use Symphony\Component\Cache\Simple\FilesystemCache;
use Symphony\Component\Cache\Simple\PhpArrayCache;
use Symphony\Component\Cache\Tests\Adapter\FilesystemAdapterTest;

/**
 * @group time-sensitive
 */
class PhpArrayCacheWithFallbackTest extends CacheTestCase
{
    protected $skippedTests = array(
        'testGetInvalidKeys' => 'PhpArrayCache does no validation',
        'testGetMultipleInvalidKeys' => 'PhpArrayCache does no validation',
        'testDeleteInvalidKeys' => 'PhpArrayCache does no validation',
        'testDeleteMultipleInvalidKeys' => 'PhpArrayCache does no validation',
        //'testSetValidData' => 'PhpArrayCache does no validation',
        'testSetInvalidKeys' => 'PhpArrayCache does no validation',
        'testSetInvalidTtl' => 'PhpArrayCache does no validation',
        'testSetMultipleInvalidKeys' => 'PhpArrayCache does no validation',
        'testSetMultipleInvalidTtl' => 'PhpArrayCache does no validation',
        'testHasInvalidKeys' => 'PhpArrayCache does no validation',
        'testPrune' => 'PhpArrayCache just proxies',
    );

    protected static $file;

    public static function setupBeforeClass()
    {
        self::$file = sys_get_temp_dir().'/symphony-cache/php-array-adapter-test.php';
    }

    protected function tearDown()
    {
        if (file_exists(sys_get_temp_dir().'/symphony-cache')) {
            FilesystemAdapterTest::rmdir(sys_get_temp_dir().'/symphony-cache');
        }
    }

    public function createSimpleCache($defaultLifetime = 0)
    {
        return new PhpArrayCache(self::$file, new FilesystemCache('php-array-fallback', $defaultLifetime));
    }
}
