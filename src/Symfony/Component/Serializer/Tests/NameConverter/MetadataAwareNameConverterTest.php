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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
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
        $nameConverter = new MetadataAwareNameConverter(new ClassMetadataFactory(new AttributeLoader()));
        $this->assertInstanceOf(NameConverterInterface::class, $nameConverter);
    }

    #[DataProvider('attributeProvider')]
    public function testNormalize(string|int $propertyName, string|int $expected)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, SerializedNameDummy::class));
    }

    #[DataProvider('fallbackAttributeProvider')]
    public function testNormalizeWithFallback(string|int $propertyName, string|int $expected)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $fallback = $this->createStub(NameConverterInterface::class);
        $fallback
            ->method('normalize')
            ->willReturnCallback(static fn ($propertyName) => strtoupper($propertyName))
        ;

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory, $fallback);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, SerializedNameDummy::class));
    }

    #[DataProvider('attributeProvider')]
    public function testDenormalize(string|int $expected, string|int $propertyName)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->denormalize($propertyName, SerializedNameDummy::class));
    }

    #[DataProvider('fallbackAttributeProvider')]
    public function testDenormalizeWithFallback(string|int $expected, string|int $propertyName)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $fallback = $this->createStub(NameConverterInterface::class);
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
            ['duux', 'duxi'],
            [0, 0],
        ];
    }

    public static function fallbackAttributeProvider(): array
    {
        return [
            ['foo', 'baz'],
            ['bar', 'qux'],
            ['quux', 'QUUX'],
            ['duux', 'duxi'],
            [0, 0],
        ];
    }

    #[DataProvider('attributeAndContextProvider')]
    public function testNormalizeWithGroups(string $propertyName, string $expected, array $context = [])
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->normalize($propertyName, OtherSerializedNameDummy::class, null, $context));
    }

    #[DataProvider('fallbackAttributeAndContextProvider')]
    public function testNormalizeWithGroupsAndFallback(string $propertyName, string $expected, array $context = [])
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $fallback = $this->createStub(NameConverterInterface::class);
        $fallback
            ->method('normalize')
            ->willReturnCallback(static fn ($propertyName) => strtoupper($propertyName))
        ;

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory, $fallback);

        $this->assertSame($expected, $nameConverter->normalize($propertyName, OtherSerializedNameDummy::class, null, $context));
    }

    #[DataProvider('attributeAndContextProvider')]
    public function testDenormalizeWithGroups(string $expected, string $propertyName, array $context = [])
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals($expected, $nameConverter->denormalize($propertyName, OtherSerializedNameDummy::class, null, $context));
    }

    #[DataProvider('denormalizeFallbackAttributeAndContextProvider')]
    public function testDenormalizeWithGroupsAndFallback(string $expected, string $propertyName, array $context = [])
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $fallback = $this->createStub(NameConverterInterface::class);
        $fallback
            ->method('denormalize')
            ->willReturnCallback(static fn ($propertyName) => strtolower($propertyName))
        ;

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory, $fallback);

        $this->assertSame($expected, $nameConverter->denormalize($propertyName, OtherSerializedNameDummy::class, null, $context));
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
            ['duux', 'duxi', ['groups' => ['*']]],
            ['duux', 'duxa', ['groups' => ['a']]],
            ['puux', 'puux', []],
            ['puux', 'puux', ['groups' => ['*']]],
            ['puux', 'puxi', ['groups' => ['i']]],
            ['puux', 'puxa', ['groups' => ['a']]],
            ['puux', 'puux', ['groups' => ['z']]],
        ];
    }

    public static function fallbackAttributeAndContextProvider(): array
    {
        return [
            ['buz', 'BUZ', ['groups' => ['a']]],
            ['buzForExport', 'buz', ['groups' => ['b']]],
            ['buz', 'BUZ', ['groups' => 'a']],
            ['buzForExport', 'buz', ['groups' => 'b']],
            ['buz', 'BUZ', ['groups' => ['c']]],
            ['buz', 'BUZ', []],
            ['buzForExport', 'buz', ['groups' => ['*']]],
            ['duux', 'duxi', ['groups' => ['*']]],
            ['duux', 'duxa', ['groups' => ['a']]],
            ['puux', 'PUUX', []],
            ['puux', 'PUUX', ['groups' => ['*']]],
            ['puux', 'puxi', ['groups' => ['i']]],
            ['puux', 'puxa', ['groups' => ['a']]],
            ['puux', 'PUUX', ['groups' => ['z']]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => [], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => [], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => [], 'enable_default_groups' => true]],
            ['customGroup', 'renamedCustomGroup', ['groups' => [], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['*'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['*'], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => ['*'], 'enable_default_groups' => true]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['*'], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
        ];
    }

    public static function denormalizeFallbackAttributeAndContextProvider(): array
    {
        return [
            ['buz', 'BUZ', ['groups' => ['a']]],
            ['buzForExport', 'buz', ['groups' => ['b']]],
            ['buz', 'BUZ', ['groups' => 'a']],
            ['buzForExport', 'buz', ['groups' => 'b']],
            ['buz', 'BUZ', ['groups' => ['c']]],
            ['buz', 'BUZ', []],
            ['buzForExport', 'buz', ['groups' => ['*']]],
            ['duux', 'duxi', ['groups' => ['*']]],
            ['duux', 'duxa', ['groups' => ['a']]],
            ['puux', 'PUUX', []],
            ['puux', 'PUUX', ['groups' => ['*']]],
            ['puux', 'puxi', ['groups' => ['i']]],
            ['puux', 'puxa', ['groups' => ['a']]],
            ['puux', 'PUUX', ['groups' => ['z']]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => [], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => [], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => [], 'enable_default_groups' => true]],
            ['customgroup', 'customGroup', ['groups' => [], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['*'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['*'], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => ['*'], 'enable_default_groups' => true]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['*'], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],
            ['customgroup', 'customGroup', ['groups' => ['Default'], 'enable_default_groups' => true]],

            ['defaultGroup', 'renamedDefaultGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],
            ['classGroup', 'renamedClassGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],
            ['noGroup', 'renamedNoGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],
            ['customgroup', 'customGroup', ['groups' => ['OtherSerializedNameDummy'], 'enable_default_groups' => true]],

            ['defaultgroup', 'defaultGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
            ['classgroup', 'classGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
            ['nogroup', 'noGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
            ['customGroup', 'renamedCustomGroup', ['groups' => ['custom'], 'enable_default_groups' => true]],
        ];
    }

    public function testDenormalizeSerializedNameOfGroupedAttributeWithoutContextGroups()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertSame('quux', $nameConverter->normalize('qux', OtherSerializedNameDummy::class));
        $this->assertSame('qux', $nameConverter->denormalize('quux', OtherSerializedNameDummy::class));
    }

    public function testDenormalizeWithCacheContext()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals('buz', $nameConverter->denormalize('buz', OtherSerializedNameDummy::class, null, ['groups' => ['a']]));
        $this->assertEquals('buzForExport', $nameConverter->denormalize('buz', OtherSerializedNameDummy::class, null, ['groups' => ['b']]));
        $this->assertEquals('buz', $nameConverter->denormalize('buz', OtherSerializedNameDummy::class));
    }

    public function testDenormalizeUngroupedSerializedNameWithStarGroup()
    {
        // BC: with the default-groups flag OFF, a property carrying SerializedName
        // but no #[Groups] must NOT be resolved when groups=['*']. Pre-feature, the
        // condition `$contextGroups && !$metadataGroups` skipped it; the rewrite
        // must preserve that.
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $this->assertEquals('renamedNoGroup', $nameConverter->denormalize('renamedNoGroup', OtherSerializedNameDummy::class, null, ['groups' => ['*']]));
    }

    public function testDenormalizeWithNestedPathAndName()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Found SerializedName and SerializedPath attributes on property "foo" of class "Symfony\Component\Serializer\Tests\NameConverter\NestedPathAndName".');
        $nameConverter->denormalize('five', NestedPathAndName::class);
    }

    public function testDenormalizeWithNestedPathAndNameSameGroup()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Found SerializedName and SerializedPath attributes on property "bar" of class "Symfony\Component\Serializer\Tests\NameConverter\NestedPathAndNameSameGroup".');
        $nameConverter->denormalize('eight', NestedPathAndNameSameGroup::class, null, ['groups' => 'a']);
    }

    public function testDenormalizeWithNestedPathAndNameDifferentGroups()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->assertSame('baz', $nameConverter->denormalize('eleven', NestedPathAndNameDifferentGroups::class, null, ['groups' => 'a']));
    }

    public function testNormalizeWithNestedPathAndName()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Found SerializedName and SerializedPath attributes on property "foo" of class "Symfony\Component\Serializer\Tests\NameConverter\NestedPathAndName".');
        $nameConverter->normalize('foo', NestedPathAndName::class);
    }

    public function testNormalizeWithNestedPathAndNameSameGroup()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Found SerializedName and SerializedPath attributes on property "bar" of class "Symfony\Component\Serializer\Tests\NameConverter\NestedPathAndNameSameGroup".');
        $nameConverter->normalize('bar', NestedPathAndNameSameGroup::class, null, ['groups' => 'a']);
    }

    public function testNormalizeWithNestedPathAndNameDifferentGroups()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $this->assertSame('eleven', $nameConverter->normalize('baz', NestedPathAndNameDifferentGroups::class, null, ['groups' => 'a']));
    }
}

class NestedPathAndName
{
    #[SerializedName('five'), SerializedPath('[one][two][three]')]
    public $foo;
}

class NestedPathAndNameSameGroup
{
    #[Groups(['a']), SerializedName('eight', 'a'), SerializedPath('[four][five][six]', 'a')]
    public $bar;
}

class NestedPathAndNameDifferentGroups
{
    #[Groups(['a', 'b']), SerializedName('eleven', 'a'), SerializedPath('[seven][eight][nine]', 'b')]
    public $baz;
}
