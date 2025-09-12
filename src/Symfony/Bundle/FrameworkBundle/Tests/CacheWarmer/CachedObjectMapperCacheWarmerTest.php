<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\CachedObjectMapperCacheWarmer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\AbstractA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;
use Symfony\Component\ObjectMapper\Tests\Fixtures\C;
use Symfony\Component\ObjectMapper\Tests\Fixtures\D;

class CachedObjectMapperCacheWarmerTest extends TestCase
{
    private ?string $cacheDir = null;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/symfony_object_mapper_'.uniqid();
        (new Filesystem())->mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        if (null !== $this->cacheDir) {
            (new Filesystem())->remove($this->cacheDir);
        }
    }

    public function testWarmUp()
    {
        $mappingPairs = [
            ['source' => A::class, 'target' => B::class],
            ['source' => C::class, 'target' => D::class],
        ];

        $warmer = new CachedObjectMapperCacheWarmer($this->cacheDir, $mappingPairs);
        $warmer->warmUp($this->cacheDir);

        $this->assertFileExists($this->cacheDir.'/'.hash('xxh128', A::class.'-to-'.B::class).'.php');
        $this->assertFileExists($this->cacheDir.'/'.hash('xxh128', C::class.'-to-'.D::class).'.php');
    }

    public function testWarmUpWithNoPairs()
    {
        $warmer = new CachedObjectMapperCacheWarmer($this->cacheDir, []);
        $warmer->warmUp($this->cacheDir);

        $this->assertEmpty(glob($this->cacheDir.'/*.php'));
    }

    public function testWarmUpWithAbstractClass()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Can not generate mapping metadata from an abstract class "Symfony\Component\ObjectMapper\Tests\Fixtures\AbstractA".');
        $mappingPairs = [
            ['source' => AbstractA::class, 'target' => B::class],
            ['source' => C::class, 'target' => D::class],
        ];

        $warmer = new CachedObjectMapperCacheWarmer($this->cacheDir, $mappingPairs);
        $warmer->warmUp($this->cacheDir);

        $this->assertFileExists($this->cacheDir.'/'.hash('xxh128', C::class.'-to-'.D::class).'.php');
        $this->assertFileDoesNotExist($this->cacheDir.'/'.hash('xxh128', AbstractA::class.'-to-'.B::class).'.php');
    }
}
