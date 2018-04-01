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

use Symphony\Component\Cache\Adapter\FilesystemAdapter;
use Symphony\Component\Cache\Adapter\PhpArrayAdapter;

/**
 * @group time-sensitive
 */
class PhpArrayAdapterWithFallbackTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testGetItemInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',
        'testGetItemsInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',
        'testHasItemInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',
        'testDeleteItemInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',
        'testDeleteItemsInvalidKeys' => 'PhpArrayAdapter does not throw exceptions on invalid key.',
        'testPrune' => 'PhpArrayAdapter just proxies',
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

    public function createCachePool($defaultLifetime = 0)
    {
        return new PhpArrayAdapter(self::$file, new FilesystemAdapter('php-array-fallback', $defaultLifetime));
    }
}
