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

use Symfony\Bridge\PhpUnit\ForwardCompatTestTrait;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\Cache\Simple\PhpArrayCache;
use Symfony\Component\Cache\Tests\Adapter\FilesystemAdapterTest;

/**
 * @group time-sensitive
 */
class PhpArrayCacheWithFallbackTest extends CacheTestCase
{
    use ForwardCompatTestTrait;

    protected $skippedTests = [
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
    ];

    protected static $file;

    private static function doSetUpBeforeClass()
    {
        self::$file = sys_get_temp_dir().'/symfony-cache/php-array-adapter-test.php';
    }

    private function doTearDown()
    {
        if (file_exists(sys_get_temp_dir().'/symfony-cache')) {
            FilesystemAdapterTest::rmdir(sys_get_temp_dir().'/symfony-cache');
        }
    }

    public function createSimpleCache($defaultLifetime = 0)
    {
        return new PhpArrayCache(self::$file, new FilesystemCache('php-array-fallback', $defaultLifetime));
    }
}
