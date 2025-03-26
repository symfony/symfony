<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Read;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Read\LazyInstantiator;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;

class LazyInstantiatorTest extends TestCase
{
    private string $lazyGhostsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lazyGhostsDir = \sprintf('%s/symfony_json_streamer_test/lazy_ghost', sys_get_temp_dir());

        if (is_dir($this->lazyGhostsDir)) {
            array_map('unlink', glob($this->lazyGhostsDir.'/*'));
            rmdir($this->lazyGhostsDir);
        }
    }

    /**
     * @requires PHP < 8.4
     */
    public function testCreateLazyGhostUsingVarExporter()
    {
        $ghost = (new LazyInstantiator($this->lazyGhostsDir))->instantiate(ClassicDummy::class, function (ClassicDummy $object): void {
            $object->id = 123;
        });

        $this->assertSame(123, $ghost->id);
    }

    /**
     * @requires PHP < 8.4
     */
    public function testCreateCacheFile()
    {
        // use DummyForLazyInstantiation class to be sure that the instantiated object is not already in cache.
        (new LazyInstantiator($this->lazyGhostsDir))->instantiate(DummyForLazyInstantiation::class, function (DummyForLazyInstantiation $object): void {});

        $this->assertCount(1, glob($this->lazyGhostsDir.'/*'));
    }

    /**
     * @requires PHP < 8.4
     */
    public function testThrowIfLazyGhostDirNotDefined()
    {
        $this->expectException(InvalidArgumentException::class);
        new LazyInstantiator();
    }

    /**
     * @requires PHP 8.4
     */
    public function testCreateLazyGhostUsingPhp()
    {
        $ghost = (new LazyInstantiator())->instantiate(ClassicDummy::class, function (ClassicDummy $object): void {
            $object->id = 123;
        });

        $this->assertSame(123, $ghost->id);
    }
}

class DummyForLazyInstantiation
{
}
