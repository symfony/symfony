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
use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

class LoaderChainTest extends TestCase
{
    public function testAllLoadersAreCalled()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = $this->createMock(LoaderInterface::class);
        $loader1->expects($this->once())
            ->method('loadClassMetadata')
            ->with($this->equalTo($metadata));

        $loader2 = $this->createMock(LoaderInterface::class);
        $loader2->expects($this->once())
            ->method('loadClassMetadata')
            ->with($this->equalTo($metadata));

        $chain = new LoaderChain([
            $loader1,
            $loader2,
        ]);

        $chain->loadClassMetadata($metadata);
    }

    public function testLoadersAreSkippedForClassesTheyDoNotMap()
    {
        $metadata = new ClassMetadata(\stdClass::class);

        $mapping = $this->createMock(LoaderInterface::class);
        $mapping->expects($this->never())
            ->method('loadClassMetadata');

        $other = $this->createMock(LoaderInterface::class);
        $other->expects($this->once())
            ->method('loadClassMetadata')
            ->with($this->equalTo($metadata));

        $chain = new LoaderChain([$mapping, $other], [0 => [\ArrayObject::class => true]]);

        $chain->loadClassMetadata($metadata);
    }

    public function testLoadersAreCalledForClassesTheyMap()
    {
        $metadata = new ClassMetadata(\stdClass::class);

        $mapping = $this->createMock(LoaderInterface::class);
        $mapping->expects($this->once())
            ->method('loadClassMetadata')
            ->with($this->equalTo($metadata));

        $chain = new LoaderChain([$mapping], [0 => [\ArrayObject::class => true, \stdClass::class => true]]);

        $chain->loadClassMetadata($metadata);
    }

    public function testMappedClassesForAnUnknownLoader()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Mapped classes are declared for loader "1", which is not part of the chain.');

        new LoaderChain([$this->createStub(LoaderInterface::class)], [1 => [\stdClass::class => true]]);
    }

    public function testReturnsTrueIfAnyLoaderReturnedTrue()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = $this->createStub(LoaderInterface::class);
        $loader1
            ->method('loadClassMetadata')
            ->willReturn(true);

        $loader2 = $this->createStub(LoaderInterface::class);
        $loader2
            ->method('loadClassMetadata')
            ->willReturn(false);

        $chain = new LoaderChain([
            $loader1,
            $loader2,
        ]);

        $this->assertTrue($chain->loadClassMetadata($metadata));
    }

    public function testReturnsFalseIfNoLoaderReturnedTrue()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = $this->createStub(LoaderInterface::class);
        $loader1
            ->method('loadClassMetadata')
            ->willReturn(false);

        $loader2 = $this->createStub(LoaderInterface::class);
        $loader2
            ->method('loadClassMetadata')
            ->willReturn(false);

        $chain = new LoaderChain([
            $loader1,
            $loader2,
        ]);

        $this->assertFalse($chain->loadClassMetadata($metadata));
    }
}
