<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\NameConverter;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Annotation\SerializedPath;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedNameDummy;
use Symfony\Component\Serializer\Tests\Fixtures\OtherSerializedNameDummy;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
final class MetadataAwareNameConverterTest extends TestCase
{
    public function testInterface()
    {
        $classMetadataFactory = $this->createMock(ClassMetadataFactoryInterface::class);
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->assertInstanceOf(NameConverterInterface::class, $nameConverter);
    }

    /**
     * @dataProvider attributeProvider
     */
    public function testNormalize($propertyName, $expected)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, SerializedNameDummy::class));
    }

    /**
     * @dataProvider fallbackAttributeProvider
     */
    public function testNormalizeWithFallback($propertyName, $expected)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $fallback = $this->createMock(NameConverterInterface::class);
        $fallback
            ->method('normalize')
            ->willReturnCallback(fn ($propertyName) => strtoupper($propertyName))
        ;

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory, $fallback);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, SerializedNameDummy::class));
    }

    /**
     * @dataProvider attributeProvider
     */
    public function testDenormalize($expected, $propertyName)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->denormalize($propertyName, SerializedNameDummy::class));
    }

    /**
     * @dataProvider fallbackAttributeProvider
     */
    public function testDenormalizeWithFallback($expected, $propertyName)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $fallback = $this->createMock(NameConverterInterface::class);
        $fallback
            ->method('denormalize')
            ->willReturnCallback(fn ($propertyName) => strtolower($propertyName))
        ;

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory, $fallback);

        $this->assertEquals($expected, $nameConverter->denormalize($propertyName, SerializedNameDummy::class));
    }

    public static function attributeProvider(): array
    {
        return [
            ['foo', 'baz'],
            ['bar', 'qux'],
            ['quux', 'quux'],
            [0, 0],
        ];
    }

    public static function fallbackAttributeProvider(): array
    {
        return [
            ['foo', 'baz'],
            ['bar', 'qux'],
            ['quux', 'QUUX'],
            [0, 0],
        ];
    }

    /**
     * @dataProvider attributeAndContextProvider
     */
    public function testNormalizeWithGroups($propertyName, $expected, $context = [])
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, OtherSerializedNameDummy::class, null, $context));
    }

    /**
     * @dataProvider attributeAndContextProvider
     */
    public function testDenormalizeWithGroups($expected, $propertyName, $context = [])
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->denormalize($propertyName, OtherSerializedNameDummy::class, null, $context));
    }

    public static function attributeAndContextProvider()
    {
        return [
            ['buz', 'buz', ['groups' => ['a']]],
            ['buzForExport', 'buz', ['groups' => ['b']]],
            ['buz', 'buz', ['groups' => 'a']],
            ['buzForExport', 'buz', ['groups' => 'b']],
            ['buz', 'buz', ['groups' => ['c']]],
            ['buz', 'buz', []],
        ];
    }

    public function testDenormalizeWithCacheContext()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals('buz', $nameConverter->denormalize('buz', OtherSerializedNameDummy::class, null, ['groups' => ['a']]));
        $this->assertEquals('buzForExport', $nameConverter->denormalize('buz', OtherSerializedNameDummy::class, null, ['groups' => ['b']]));
        $this->assertEquals('buz', $nameConverter->denormalize('buz', OtherSerializedNameDummy::class));
    }

    public function testDenormalizeWithNestedPathAndName()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Found SerializedName and SerializedPath annotations on property "foo" of class "Symfony\Component\Serializer\Tests\NameConverter\NestedPathAndName".');
        $nameConverter->denormalize('foo', NestedPathAndName::class);
    }

    public function testNormalizeWithNestedPathAndName()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Found SerializedName and SerializedPath annotations on property "foo" of class "Symfony\Component\Serializer\Tests\NameConverter\NestedPathAndName".');
        $nameConverter->normalize('foo', NestedPathAndName::class);
    }
}

class NestedPathAndName
{
    /**
     * @SerializedName("five")
     *
     * @SerializedPath("[one][two][three]")
     */
    public $foo;
}
