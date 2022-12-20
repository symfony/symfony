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
        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        self::assertInstanceOf(ClassMetadataFactoryInterface::class, $compiledClassMetadataFactory);
    }

    public function testItThrowAnExceptionWhenCacheFileIsNotFound()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessageMatches('#File ".*/Fixtures/not-found-serializer.class.metadata.php" could not be found.#');

        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/not-found-serializer.class.metadata.php', $classMetadataFactory);
    }

    public function testItThrowAnExceptionWhenMetadataIsNotOfTypeArray()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Compiled metadata must be of the type array, object given.');

        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/object-metadata.php', $classMetadataFactory);
    }

    /**
     * @dataProvider valueProvider
     */
    public function testItReturnsTheCompiledMetadata($value)
    {
        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $classMetadataFactory
            ->expects(self::never())
            ->method('getMetadataFor')
        ;

        $expected = new ClassMetadata(Dummy::class);
        $expected->addAttributeMetadata(new AttributeMetadata('foo'));
        $expected->addAttributeMetadata(new AttributeMetadata('bar'));
        $expected->addAttributeMetadata(new AttributeMetadata('baz'));
        $expected->addAttributeMetadata(new AttributeMetadata('qux'));

        self::assertEquals($expected, $compiledClassMetadataFactory->getMetadataFor($value));
    }

    public function testItDelegatesGetMetadataForCall()
    {
        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $classMetadata = new ClassMetadata(SerializedNameDummy::class);

        $classMetadataFactory
            ->expects(self::once())
            ->method('getMetadataFor')
            ->with(SerializedNameDummy::class)
            ->willReturn($classMetadata)
        ;

        self::assertEquals($classMetadata, $compiledClassMetadataFactory->getMetadataFor(SerializedNameDummy::class));
    }

    public function testItReturnsTheSameInstance()
    {
        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        self::assertSame($compiledClassMetadataFactory->getMetadataFor(Dummy::class), $compiledClassMetadataFactory->getMetadataFor(Dummy::class));
    }

    /**
     * @dataProvider valueProvider
     */
    public function testItHasMetadataFor($value)
    {
        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $classMetadataFactory
            ->expects(self::never())
            ->method('hasMetadataFor')
        ;

        self::assertTrue($compiledClassMetadataFactory->hasMetadataFor($value));
    }

    public function testItDelegatesHasMetadataForCall()
    {
        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        $compiledClassMetadataFactory = new CompiledClassMetadataFactory(__DIR__.'/../../Fixtures/serializer.class.metadata.php', $classMetadataFactory);

        $classMetadataFactory
            ->expects(self::once())
            ->method('hasMetadataFor')
            ->with(SerializedNameDummy::class)
            ->willReturn(true)
        ;

        self::assertTrue($compiledClassMetadataFactory->hasMetadataFor(SerializedNameDummy::class));
    }

    public function valueProvider()
    {
        return [
            [Dummy::class],
            [new Dummy()],
        ];
    }
}
