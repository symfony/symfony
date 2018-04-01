<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\Mapping\ClassMetadata;
use Symphony\Component\Validator\Mapping\Loader\LoaderInterface;

class FilesLoaderTest extends TestCase
{
    public function testCallsGetFileLoaderInstanceForeachPath()
    {
        $loader = $this->getFilesLoader($this->getFileLoader());
        $this->assertEquals(4, $loader->getTimesCalled());
    }

    public function testCallsActualFileLoaderForMetadata()
    {
        $fileLoader = $this->getFileLoader();
        $fileLoader->expects($this->exactly(4))
            ->method('loadClassMetadata');
        $loader = $this->getFilesLoader($fileLoader);
        $loader->loadClassMetadata(new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity'));
    }

    public function getFilesLoader(LoaderInterface $loader)
    {
        return $this->getMockForAbstractClass('Symphony\Component\Validator\Tests\Fixtures\FilesLoader', array(array(
            __DIR__.'/constraint-mapping.xml',
            __DIR__.'/constraint-mapping.yaml',
            __DIR__.'/constraint-mapping.test',
            __DIR__.'/constraint-mapping.txt',
        ), $loader));
    }

    public function getFileLoader()
    {
        return $this->getMockBuilder('Symphony\Component\Validator\Mapping\Loader\LoaderInterface')->getMock();
    }
}
