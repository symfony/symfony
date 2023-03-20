<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\CacheWarmer\LazyGhostCacheWarmer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;

class LazyGhostCacheWarmerTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sprintf('%s/symfony_test', sys_get_temp_dir());

        $lazyGhostCacheDir = $this->cacheDir.'/json_encoder/lazy_ghost';

        if (is_dir($lazyGhostCacheDir)) {
            array_map('unlink', glob($lazyGhostCacheDir.'/*'));
            rmdir($lazyGhostCacheDir);
        }
    }

    public function testWarmUpLazyGhost()
    {
        $lazyGhostCacheDir = $this->cacheDir.'/json_encoder/lazy_ghost';

        (new LazyGhostCacheWarmer([ClassicDummy::class], $this->cacheDir))->warmUp('useless');

        $this->assertSame(
            array_map(fn (string $c): string => sprintf('%s/%s.php', $lazyGhostCacheDir, hash('xxh128', $c)), [ClassicDummy::class]),
            glob($lazyGhostCacheDir.'/*'),
        );
    }
}
