<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\CacheClearer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheClearer\ChainCacheClearer;

class ChainCacheClearerTest extends TestCase
{
    protected static string $cacheDir;

    public static function setUpBeforeClass(): void
    {
        self::$cacheDir = tempnam(sys_get_temp_dir(), 'sf_cache_clearer_dir');
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$cacheDir);
    }

    public function testInjectClearersInConstructor()
    {
        $clearer = $this->createMock(CacheClearerInterface::class);
        $clearer
            ->expects($this->once())
            ->method('clear');

        $chainClearer = new ChainCacheClearer([$clearer]);
        $chainClearer->clear(self::$cacheDir);
    }
}
