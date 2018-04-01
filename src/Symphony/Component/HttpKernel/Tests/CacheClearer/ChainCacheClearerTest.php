<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\CacheClearer;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\CacheClearer\ChainCacheClearer;

class ChainCacheClearerTest extends TestCase
{
    protected static $cacheDir;

    public static function setUpBeforeClass()
    {
        self::$cacheDir = tempnam(sys_get_temp_dir(), 'sf2_cache_clearer_dir');
    }

    public static function tearDownAfterClass()
    {
        @unlink(self::$cacheDir);
    }

    public function testInjectClearersInConstructor()
    {
        $clearer = $this->getMockClearer();
        $clearer
            ->expects($this->once())
            ->method('clear');

        $chainClearer = new ChainCacheClearer(array($clearer));
        $chainClearer->clear(self::$cacheDir);
    }

    protected function getMockClearer()
    {
        return $this->getMockBuilder('Symphony\Component\HttpKernel\CacheClearer\CacheClearerInterface')->getMock();
    }
}
