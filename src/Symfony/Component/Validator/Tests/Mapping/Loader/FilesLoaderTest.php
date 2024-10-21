<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;
use Symfony\Component\Validator\Tests\Fixtures\FilesLoader;

class FilesLoaderTest extends TestCase
{
    public function testCallsGetFileLoaderInstanceForeachPath()
    {
        $loader = $this->getFilesLoader($this->createMock(LoaderInterface::class));
        $this->assertEquals(4, $loader->getTimesCalled());
    }

    public function testCallsActualFileLoaderForMetadata()
    {
        $fileLoader = $this->createMock(LoaderInterface::class);
        $fileLoader->expects($this->exactly(4))
            ->method('loadClassMetadata');
        $loader = $this->getFilesLoader($fileLoader);
        $loader->loadClassMetadata(new ClassMetadata(Entity::class));
    }

    public function getFilesLoader(LoaderInterface $loader)
    {
        return new class([
            __DIR__.'/constraint-mapping.xml',
            __DIR__.'/constraint-mapping.yaml',
            __DIR__.'/constraint-mapping.test',
            __DIR__.'/constraint-mapping.txt',
        ], $loader) extends FilesLoader {};
    }
}
