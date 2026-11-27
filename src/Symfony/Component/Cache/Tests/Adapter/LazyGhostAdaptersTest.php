<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Filesystem\Filesystem;

#[RunTestsInSeparateProcesses]
class LazyGhostAdaptersTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    public function testNullAdapterGet()
    {
        $this->assertSame('bar', self::createLazyGhost(NullAdapter::class)->get('foo', static fn () => 'bar'));
    }

    public function testNullAdapterGetItem()
    {
        $this->assertFalse(self::createLazyGhost(NullAdapter::class)->getItem('foo')->isHit());
    }

    public function testNullAdapterGetItems()
    {
        $items = self::createLazyGhost(NullAdapter::class)->getItems(['foo']);

        $this->assertFalse(iterator_to_array($items)['foo']->isHit());
    }

    public function testAbstractAdapterCommit()
    {
        $this->assertTrue(self::createLazyGhost(FilesystemAdapter::class)->commit());
    }

    public function testAbstractTagAwareAdapterCommit()
    {
        $this->assertTrue(self::createLazyGhost(FilesystemTagAwareAdapter::class)->commit());
    }

    private static function createLazyGhost(string $class): object
    {
        return (new \ReflectionClass($class))->newLazyGhost(static function (object $ghost) {
            $ghost->__construct();
        });
    }
}
