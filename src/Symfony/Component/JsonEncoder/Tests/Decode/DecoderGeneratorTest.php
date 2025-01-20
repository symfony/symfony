<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Decode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Decode\DecoderGenerator;
use Symfony\Component\JsonEncoder\Exception\UnsupportedException;
use Symfony\Component\JsonEncoder\Mapping\Decode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\Decode\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\BooleanStringDenormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\DivideStringAndCastToIntDenormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithOtherDummies;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties;
use Symfony\Component\JsonEncoder\Tests\ServiceContainer;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class DecoderGeneratorTest extends TestCase
{
    private string $decodersDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decodersDir = \sprintf('%s/symfony_json_encoder_test/decoder', sys_get_temp_dir());

        if (is_dir($this->decodersDir)) {
            array_map('unlink', glob($this->decodersDir.'/*'));
            rmdir($this->decodersDir);
        }
    }

    /**
     * @dataProvider generatedDecoderDataProvider
     */
    public function testGeneratedDecoder(string $fixture, Type $type)
    {
        $propertyMetadataLoader = new GenericTypePropertyMetadataLoader(
            new DateTimeTypePropertyMetadataLoader(new AttributePropertyMetadataLoader(
                new PropertyMetadataLoader(TypeResolver::create()),
                new ServiceContainer([
                    DivideStringAndCastToIntDenormalizer::class => new DivideStringAndCastToIntDenormalizer(),
                    BooleanStringDenormalizer::class => new BooleanStringDenormalizer(),
                ]),
                TypeResolver::create(),
            )),
            new TypeContextFactory(new StringTypeResolver()),
        );

        $generator = new DecoderGenerator($propertyMetadataLoader, $this->decodersDir);

        $this->assertStringEqualsFile(
            \sprintf('%s/Fixtures/decoder/%s.php', \dirname(__DIR__), $fixture),
            file_get_contents($generator->generate($type, false)),
        );

        $this->assertStringEqualsFile(
            \sprintf('%s/Fixtures/decoder/%s.stream.php', \dirname(__DIR__), $fixture),
            file_get_contents($generator->generate($type, true)),
        );
    }

    /**
     * @return iterable<array{0: string, 1: Type}>
     */
    public static function generatedDecoderDataProvider(): iterable
    {
        yield ['scalar', Type::int()];
        yield ['mixed', Type::mixed()];
        yield ['null', Type::null()];
        yield ['backed_enum', Type::enum(DummyBackedEnum::class)];
        yield ['nullable_backed_enum', Type::nullable(Type::enum(DummyBackedEnum::class))];

        yield ['list', Type::list()];
        yield ['object_list', Type::list(Type::object(ClassicDummy::class))];
        yield ['nullable_object_list', Type::nullable(Type::list(Type::object(ClassicDummy::class)))];

        yield ['dict', Type::dict()];
        yield ['object_dict', Type::dict(Type::object(ClassicDummy::class))];
        yield ['nullable_object_dict', Type::nullable(Type::dict(Type::object(ClassicDummy::class)))];

        yield ['iterable', Type::iterable()];
        yield ['object_iterable', Type::iterable(Type::object(ClassicDummy::class))];

        yield ['object', Type::object(ClassicDummy::class)];
        yield ['nullable_object', Type::nullable(Type::object(ClassicDummy::class))];
        yield ['object_in_object', Type::object(DummyWithOtherDummies::class)];
        yield ['object_with_nullable_properties', Type::object(DummyWithNullableProperties::class)];
        yield ['object_with_denormalizer', Type::object(DummyWithNormalizerAttributes::class)];

        yield ['union', Type::union(Type::int(), Type::list(Type::enum(DummyBackedEnum::class)), Type::object(DummyWithNameAttributes::class))];
        yield ['object_with_union', Type::object(DummyWithUnionProperties::class)];
    }

    public function testDoNotSupportIntersectionType()
    {
        $generator = new DecoderGenerator(new PropertyMetadataLoader(TypeResolver::create()), $this->decodersDir);

        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage('"Stringable&Traversable" type is not supported.');

        $generator->generate(Type::intersection(Type::object(\Traversable::class), Type::object(\Stringable::class)), false);
    }

    public function testDoNotSupportEnumType()
    {
        $generator = new DecoderGenerator(new PropertyMetadataLoader(TypeResolver::create()), $this->decodersDir);

        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage(\sprintf('"%s" type is not supported.', DummyEnum::class));

        $generator->generate(Type::enum(DummyEnum::class), false);
    }

    public function testCallPropertyMetadataLoaderWithProperContext()
    {
        $type = Type::object(self::class);

        $propertyMetadataLoader = $this->createMock(PropertyMetadataLoaderInterface::class);
        $propertyMetadataLoader->expects($this->once())
            ->method('load')
            ->with(self::class, [], [
                'original_type' => $type,
                'generated_classes' => [(string) $type => true],
            ])
            ->willReturn([]);

        $generator = new DecoderGenerator($propertyMetadataLoader, $this->decodersDir);
        $generator->generate($type, false);
    }
}
