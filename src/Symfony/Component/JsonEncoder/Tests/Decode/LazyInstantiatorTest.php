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
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes;

class LazyInstantiatorTest extends TestCase
{
    private string $lazyGhostsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lazyGhostsDir = \sprintf('%s/symfony_json_encoder_test/lazy_ghost', sys_get_temp_dir());

        if (is_dir($this->lazyGhostsDir)) {
            array_map('unlink', glob($this->lazyGhostsDir.'/*'));
            rmdir($this->lazyGhostsDir);
        }
    }

    public function testCreateLazyGhost()
    {
        $ghost = (new LazyInstantiator($this->lazyGhostsDir))->instantiate(ClassicDummy::class, []);

        $this->assertArrayHasKey(\sprintf("\0%sGhost\0lazyObjectState", preg_replace('/\\\\/', '', ClassicDummy::class)), (array) $ghost);
    }

    public function testCreateCacheFile()
    {
        (new LazyInstantiator($this->lazyGhostsDir))->instantiate(DummyWithNormalizerAttributes::class, []);

        $this->assertCount(1, glob($this->lazyGhostsDir.'/*'));
    }
}
