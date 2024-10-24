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
     * @group wip
     * @dataProvider attributeAndContextProvider
     */
    public function testNormalizeWithGroups(string $propertyName, string $expected, array $context = [])
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, OtherSerializedNameDummy::class, null, $context));
    }

    /**
     * @group wip
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
            ['buzForExport', 'buz', ['groups' => ['b']]],
            ['buz', 'buz', ['groups' => 'a']],
            ['buzForExport', 'buz', ['groups' => 'b']],
            ['buz', 'buz', ['groups' => ['c']]],
            ['buz', 'buz', []],
            ['buzForExport', 'buz', ['groups' => ['*']]],

            ['defaultGroup', 'defaultGroup', ['groups' => []]],
            ['classGroup', 'classGroup', ['groups' => []]],
            ['noGroup', 'renamedNoGroup', ['groups' => []]],
            ['customGroup', 'customGroup', ['groups' => []]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['*']]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['*']]],
            ['noGroup', 'renamedNoGroup', ['groups' => ['*']]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['*']]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['Default']]],
            ['classGroup', 'classGroup', ['groups' => ['Default']]],
            ['noGroup', 'noGroup', ['groups' => ['Default']]],
            ['customGroup', 'customGroup', ['groups' => ['Default']]],

            ['defaultGroup', 'defaultGroup', ['groups' => ['OtherSerializedNameDummy']]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['OtherSerializedNameDummy']]],
            ['noGroup', 'noGroup', ['groups' => ['OtherSerializedNameDummy']]],
            ['customGroup', 'customGroup', ['groups' => ['OtherSerializedNameDummy']]],

            ['defaultGroup', 'defaultGroup', ['groups' => ['custom']]],
            ['classGroup', 'classGroup', ['groups' => ['custom']]],
            ['noGroup', 'noGroup', ['groups' => ['custom']]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['custom']]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => [], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => [], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => [], 'enable_default_groups' => true]],
            ['customGroup', 'customGroup', ['groups' => [], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['*'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['*'], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => ['*'], 'enable_default_groups' => true]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['*'], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],
            ['noGroup', 'noGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],
            ['customGroup', 'customGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],
            ['noGroup', 'noGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],
            ['customGroup', 'customGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],

            ['defaultGroup', 'defaultGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
            ['classGroup', 'classGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
            ['noGroup', 'noGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
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
