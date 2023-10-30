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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SerializedNameDummy;
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
    public function testNormalize(string|int $propertyName, string|int $expected)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, SerializedNameDummy::class));
    }

    /**
     * @dataProvider fallbackAttributeProvider
     */
    public function testNormalizeWithFallback(string|int $propertyName, string|int $expected)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $fallback = $this->createMock(NameConverterInterface::class);
        $fallback
            ->method('normalize')
            ->willReturnCallback(static fn ($propertyName) => strtoupper($propertyName))
        ;

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory, $fallback);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, SerializedNameDummy::class));
    }

    /**
     * @dataProvider attributeProvider
     */
    public function testDenormalize(string|int $expected, string|int $propertyName)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->denormalize($propertyName, SerializedNameDummy::class));
    }

    /**
     * @dataProvider fallbackAttributeProvider
     */
    public function testDenormalizeWithFallback(string|int $expected, string|int $propertyName)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $fallback = $this->createMock(NameConverterInterface::class);
        $fallback
            ->method('denormalize')
            ->willReturnCallback(static fn ($propertyName) => strtolower($propertyName))
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
    public function testNormalizeWithGroups(string $propertyName, string $expected, array $context = [])
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, OtherSerializedNameDummy::class, null, $context));
    }

    /**
     * @dataProvider attributeAndContextProvider
     */
    public function testDenormalizeWithGroups(string $expected, string $propertyName, array $context = [])
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->denormalize($propertyName, OtherSerializedNameDummy::class, null, $context));
    }

    public static function attributeAndContextProvider(): array
    {
        return [
            ['buz', 'buz', ['groups' => ['a']]],
            ['buzForExport', 'buz', ['groups' => ['b']]],
            ['buz', 'buz', ['groups' => 'a']],
            ['buzForExport', 'buz', ['groups' => 'b']],
            ['buz', 'buz', ['groups' => ['c']]],
            ['buz', 'buz', []],
            ['buzForExport', 'buz', ['groups' => ['*']]],
        ];
    }

    public function testDenormalizeWithCacheContext()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals('buz', $nameConverter->denormalize('buz', OtherSerializedNameDummy::class, null, ['groups' => ['a']]));
        $this->assertEquals('buzForExport', $nameConverter->denormalize('buz', OtherSerializedNameDummy::class, null, ['groups' => ['b']]));
        $this->assertEquals('buz', $nameConverter->denormalize('buz', OtherSerializedNameDummy::class));
    }

    public function testDenormalizeWithNestedPathAndName()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Found SerializedName and SerializedPath attributes on property "foo" of class "Symfony\Component\Serializer\Tests\NameConverter\NestedPathAndName".');
        $nameConverter->denormalize('foo', NestedPathAndName::class);
    }

    public function testNormalizeWithNestedPathAndName()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Found SerializedName and SerializedPath attributes on property "foo" of class "Symfony\Component\Serializer\Tests\NameConverter\NestedPathAndName".');
        $nameConverter->normalize('foo', NestedPathAndName::class);
    }
}

class NestedPathAndName
{
    #[SerializedName('five'), SerializedPath('[one][two][three]')]
    public $foo;
}
