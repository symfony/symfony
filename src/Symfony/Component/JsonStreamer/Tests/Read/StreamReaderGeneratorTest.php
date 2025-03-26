<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Read;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\UnsupportedException;
use Symfony\Component\JsonStreamer\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\Mapping\Read\AttributePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\Read\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Read\StreamReaderGenerator;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyEnum;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUnionProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\DivideStringAndCastToIntValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\StringToBooleanValueTransformer;
use Symfony\Component\JsonStreamer\Tests\ServiceContainer;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class StreamReaderGeneratorTest extends TestCase
{
    private string $streamReadersDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamReadersDir = \sprintf('%s/symfony_json_streamer_test/stream_reader', sys_get_temp_dir());

        if (is_dir($this->streamReadersDir)) {
            array_map('unlink', glob($this->streamReadersDir.'/*'));
            rmdir($this->streamReadersDir);
        }
    }

    /**
     * @dataProvider generatedStreamReaderDataProvider
     */
    public function testGeneratedStreamReader(string $fixture, Type $type)
    {
        $propertyMetadataLoader = new GenericTypePropertyMetadataLoader(
            new DateTimeTypePropertyMetadataLoader(new AttributePropertyMetadataLoader(
                new PropertyMetadataLoader(TypeResolver::create()),
                new ServiceContainer([
                    DivideStringAndCastToIntValueTransformer::class => new DivideStringAndCastToIntValueTransformer(),
                    StringToBooleanValueTransformer::class => new StringToBooleanValueTransformer(),
                ]),
                TypeResolver::create(),
            )),
            new TypeContextFactory(new StringTypeResolver()),
        );

        $generator = new StreamReaderGenerator($propertyMetadataLoader, $this->streamReadersDir);

        $this->assertStringEqualsFile(
            \sprintf('%s/Fixtures/stream_reader/%s.php', \dirname(__DIR__), $fixture),
            file_get_contents($generator->generate($type, false)),
        );

        $this->assertStringEqualsFile(
            \sprintf('%s/Fixtures/stream_reader/%s.stream.php', \dirname(__DIR__), $fixture),
            file_get_contents($generator->generate($type, true)),
        );
    }

    /**
     * @return iterable<array{0: string, 1: Type}>
     */
    public static function generatedStreamReaderDataProvider(): iterable
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
        yield ['object_with_value_transformer', Type::object(DummyWithValueTransformerAttributes::class)];

        yield ['union', Type::union(Type::int(), Type::list(Type::enum(DummyBackedEnum::class)), Type::object(DummyWithNameAttributes::class))];
        yield ['object_with_union', Type::object(DummyWithUnionProperties::class)];
    }

    public function testDoNotSupportIntersectionType()
    {
        $generator = new StreamReaderGenerator(new PropertyMetadataLoader(TypeResolver::create()), $this->streamReadersDir);

        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage('"Stringable&Traversable" type is not supported.');

        $generator->generate(Type::intersection(Type::object(\Traversable::class), Type::object(\Stringable::class)), false);
    }

    public function testDoNotSupportEnumType()
    {
        $generator = new StreamReaderGenerator(new PropertyMetadataLoader(TypeResolver::create()), $this->streamReadersDir);

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

        $generator = new StreamReaderGenerator($propertyMetadataLoader, $this->streamReadersDir);
        $generator->generate($type, false);
    }
}
