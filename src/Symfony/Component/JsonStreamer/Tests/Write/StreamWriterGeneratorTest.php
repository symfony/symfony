<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Write;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\UnsupportedException;
use Symfony\Component\JsonStreamer\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\Mapping\Write\AttributePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\Write\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyEnum;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Mapping\SyntheticPropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithArray;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDollarNamedProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedArray;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithSyntheticProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUnionProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\BooleanToStringValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\DoubleIntAndCastToStringValueTransformer;
use Symfony\Component\JsonStreamer\Tests\ServiceContainer;
use Symfony\Component\JsonStreamer\Write\StreamWriterGenerator;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class StreamWriterGeneratorTest extends TestCase
{
    private string $streamWritersDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamWritersDir = \sprintf('%s/symfony_json_streamer_test/stream_writer', sys_get_temp_dir());

        if (is_dir($this->streamWritersDir)) {
            array_map('unlink', glob($this->streamWritersDir.'/*'));
            rmdir($this->streamWritersDir);
        }
    }

    #[DataProvider('generatedStreamWriterDataProvider')]
    public function testGeneratedStreamWriter(string $fixture, Type $type, ?PropertyMetadataLoaderInterface $propertyMetadataLoader = null)
    {
        $propertyMetadataLoader ??= new GenericTypePropertyMetadataLoader(
            new DateTimeTypePropertyMetadataLoader(new AttributePropertyMetadataLoader(
                new PropertyMetadataLoader(TypeResolver::create()),
                new ServiceContainer([
                    DoubleIntAndCastToStringValueTransformer::class => new DoubleIntAndCastToStringValueTransformer(),
                    BooleanToStringValueTransformer::class => new BooleanToStringValueTransformer(),
                ]),
                TypeResolver::create(),
            )),
            new TypeContextFactory(new StringTypeResolver()),
        );

        $generator = new StreamWriterGenerator($propertyMetadataLoader, $this->streamWritersDir);

        $this->assertStringEqualsFile(
            \sprintf('%s/Fixtures/stream_writer/%s.php', \dirname(__DIR__), $fixture),
            file_get_contents($generator->generate($type)),
        );
    }

    /**
     * @return iterable<array{0: string, 1: Type, 2?: PropertyMetadataLoaderInterface}>
     */
    public static function generatedStreamWriterDataProvider(): iterable
    {
        yield ['scalar', Type::int()];
        yield ['null', Type::null()];
        yield ['bool', Type::bool()];
        yield ['mixed', Type::mixed()];
        yield ['backed_enum', Type::enum(DummyBackedEnum::class, Type::string())];
        yield ['nullable_backed_enum', Type::nullable(Type::enum(DummyBackedEnum::class, Type::string()))];

        yield ['list', Type::list()];
        yield ['bool_list', Type::list(Type::bool())];
        yield ['null_list', Type::list(Type::null())];
        yield ['object_list', Type::list(Type::object(DummyWithNameAttributes::class))];
        yield ['nullable_object_list', Type::nullable(Type::list(Type::object(DummyWithNameAttributes::class)))];
        yield ['nested_list', Type::list(Type::object(DummyWithArray::class))];
        yield ['double_nested_list', Type::list(Type::object(DummyWithNestedArray::class))];

        yield ['dict', Type::dict()];
        yield ['object_dict', Type::dict(Type::object(DummyWithNameAttributes::class))];
        yield ['nullable_object_dict', Type::nullable(Type::dict(Type::object(DummyWithNameAttributes::class)))];

        yield ['iterable', Type::iterable()];
        yield ['object_iterable', Type::iterable(Type::object(ClassicDummy::class))];

        yield ['object', Type::object(DummyWithNameAttributes::class)];
        yield ['nullable_object', Type::nullable(Type::object(DummyWithNameAttributes::class))];
        yield ['object_in_object', Type::object(DummyWithOtherDummies::class)];
        yield ['object_with_value_transformer', Type::object(DummyWithValueTransformerAttributes::class)];
        yield ['self_referencing_object', Type::object(SelfReferencingDummy::class)];
        yield ['object_with_dollar_named_properties', Type::object(DummyWithDollarNamedProperties::class)];
        yield ['object_with_synthetic_properties', Type::object(DummyWithSyntheticProperties::class), new SyntheticPropertyMetadataLoader()];

        yield ['union', Type::union(Type::int(), Type::list(Type::enum(DummyBackedEnum::class)), Type::object(DummyWithNameAttributes::class))];
        yield ['object_with_union', Type::object(DummyWithUnionProperties::class)];
    }

    public function testDoNotSupportIntersectionType()
    {
        $generator = new StreamWriterGenerator(new PropertyMetadataLoader(TypeResolver::create()), $this->streamWritersDir);

        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage('"Stringable&Traversable" type is not supported.');

        $generator->generate(Type::intersection(Type::object(\Traversable::class), Type::object(\Stringable::class)));
    }

    public function testDoNotSupportEnumType()
    {
        $generator = new StreamWriterGenerator(new PropertyMetadataLoader(TypeResolver::create()), $this->streamWritersDir);

        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage(\sprintf('"%s" type is not supported.', DummyEnum::class));

        $generator->generate(Type::enum(DummyEnum::class));
    }

    public function testCallPropertyMetadataLoaderWithProperContext()
    {
        $type = Type::object(self::class);

        $propertyMetadataLoader = $this->createMock(PropertyMetadataLoaderInterface::class);
        $propertyMetadataLoader->expects($this->once())
            ->method('load')
            ->with(self::class, [], [
                'original_type' => $type,
                'generated_classes' => [self::class => true],
                'depth' => 0,
            ])
            ->willReturn([]);

        $generator = new StreamWriterGenerator($propertyMetadataLoader, $this->streamWritersDir);
        $generator->generate($type);
    }
}
