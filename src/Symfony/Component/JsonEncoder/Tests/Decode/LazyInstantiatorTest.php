<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Decode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Decode\LazyInstantiator;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithFormatterAttributes;

class LazyInstantiatorTest extends TestCase
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

    public function testCreateLazyGhost()
    {
        $ghost = (new LazyInstantiator($this->cacheDir))->instantiate(ClassicDummy::class, []);

        $this->assertArrayHasKey(sprintf("\0%sGhost\0lazyObjectState", preg_replace('/\\\\/', '', ClassicDummy::class)), (array) $ghost);
    }

    public function testCreateCacheFile()
    {
        $lazyGhostCacheDir = $this->cacheDir.'/json_encoder/lazy_ghost';

        (new LazyInstantiator($this->cacheDir))->instantiate(DummyWithFormatterAttributes::class, []);

        $this->assertCount(1, glob($lazyGhostCacheDir.'/*'));
    }
}
