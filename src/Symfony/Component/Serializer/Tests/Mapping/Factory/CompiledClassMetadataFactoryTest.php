<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Factory\CompiledClassMetadataFactory;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedNameDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Dummy;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
final class CompiledClassMetadataFactoryTest extends TestCase
{
    public function testItImplementsClassMetadataFactoryInterface()
    {
        $classMetadataFactory = $this->createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $this->assertInstanceOf(ClassMetadataFactoryInterface::class, $compiledClassMetadataFactory);
    }

    public function testItThrowAnExceptionWhenCacheFileIsNotFound()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#File ".*/Fixtures/not-found-serializer.class.metadata.php" could not be found.#');

        $classMetadataFactory = $this->createMock(ClassMetadataFactoryInterface::class);
        new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/not-found-serializer.class.metadata.php', $classMetadataFactory);
    }

    public function testItThrowAnExceptionWhenMetadataIsNotOfTypeArray()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Compiled metadata must be of the type array, object given.');

        $classMetadataFactory = $this->createMock(ClassMetadataFactoryInterface::class);
        new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/object-metadata.php', $classMetadataFactory);
    }

    /**
     * @dataProvider valueProvider
     */
    public function testItReturnsTheCompiledMetadata($value)
    {
        $classMetadataFactory = $this->createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $classMetadataFactory
            ->expects($this->never())
            ->method('getMetadataFor')
        ;

        $expected = new ClassMetadata(Dummy::class);
        $expected->addAttributeMetadata(new AttributeMetadata('foo'));
        $expected->addAttributeMetadata(new AttributeMetadata('bar'));
        $expected->addAttributeMetadata(new AttributeMetadata('baz'));
        $expected->addAttributeMetadata(new AttributeMetadata('qux'));

        $this->assertEquals($expected, $compiledClassMetadataFactory->getMetadataFor($value));
    }

    public function testItDelegatesGetMetadataForCall()
    {
        $classMetadataFactory = $this->createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $classMetadata = new ClassMetadata(SerializedNameDummy::class);

        $classMetadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with(SerializedNameDummy::class)
            ->willReturn($classMetadata)
        ;

        $this->assertEquals($classMetadata, $compiledClassMetadataFactory->getMetadataFor(SerializedNameDummy::class));
    }

    public function testItReturnsTheSameInstance()
    {
        $classMetadataFactory = $this->createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $this->assertSame($compiledClassMetadataFactory->getMetadataFor(Dummy::class), $compiledClassMetadataFactory->getMetadataFor(Dummy::class));
    }

    /**
     * @dataProvider valueProvider
     */
    public function testItHasMetadataFor($value)
    {
        $classMetadataFactory = $this->createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $classMetadataFactory
            ->expects($this->never())
            ->method('hasMetadataFor')
        ;

        $this->assertTrue($compiledClassMetadataFactory->hasMetadataFor($value));
    }

    public function testItDelegatesHasMetadataForCall()
    {
        $classMetadataFactory = $this->createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $classMetadataFactory
            ->expects($this->once())
            ->method('hasMetadataFor')
            ->with(SerializedNameDummy::class)
            ->willReturn(true)
        ;

        $this->assertTrue($compiledClassMetadataFactory->hasMetadataFor(SerializedNameDummy::class));
    }

    public static function valueProvider()
    {
        return [
            [Dummy::class],
            [new Dummy()],
        ];
    }
}
