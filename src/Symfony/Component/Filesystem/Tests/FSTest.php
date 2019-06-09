<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\FS;

/**
 * Test class for Filesystem.
 */
class FSTest extends FilesystemTest
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = (new \ReflectionClass(FS::class))->newInstanceWithoutConstructor();
    }

    public function testCanInjectTheInstanceUsed(): void
    {
        $stub = $this->createMock(Filesystem::class);

        $stub->method('exists')->willReturn(true);

        $this->assertNull(FS::getInstance());

        FS::setInstance($stub);

        $this->assertSame($stub, FS::getInstance());

        $this->assertTrue(FS::exists('foo'));
    }
}
