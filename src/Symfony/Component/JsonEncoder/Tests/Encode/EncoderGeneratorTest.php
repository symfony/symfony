<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Encode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Encode\EncoderGenerator;
use Symfony\Component\JsonEncoder\Exception\UnsupportedException;
use Symfony\Component\JsonEncoder\Mapping\Encode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\Encode\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithOtherDummies;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer\BooleanStringNormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer\DoubleIntAndCastToStringNormalizer;
use Symfony\Component\JsonEncoder\Tests\ServiceContainer;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class EncoderGeneratorTest extends TestCase
{
    private string $encodersDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encodersDir = \sprintf('%s/symfony_json_encoder_test/encoder', sys_get_temp_dir());

        if (is_dir($this->encodersDir)) {
            array_map('unlink', glob($this->encodersDir.'/*'));
            rmdir($this->encodersDir);
        }
    }

    /**
     * @dataProvider generatedEncoderDataProvider
     */
    public function testGeneratedEncoder(string $fixture, Type $type)
    {
        $propertyMetadataLoader = new GenericTypePropertyMetadataLoader(
            new DateTimeTypePropertyMetadataLoader(new AttributePropertyMetadataLoader(
                new PropertyMetadataLoader(TypeResolver::create()),
                new ServiceContainer([
                    DoubleIntAndCastToStringNormalizer::class => new DoubleIntAndCastToStringNormalizer(),
                    BooleanStringNormalizer::class => new BooleanStringNormalizer(),
                ]),
                TypeResolver::create(),
            )),
            new TypeContextFactory(new StringTypeResolver()),
        );

        $generator = new EncoderGenerator($propertyMetadataLoader, $this->encodersDir);

        $this->assertStringEqualsFile(
            \sprintf('%s/Fixtures/encoder/%s.php', \dirname(__DIR__), $fixture),
            file_get_contents($generator->generate($type)),
        );
    }

    /**
     * @return iterable<array{0: string, 1: Type}>
     */
    public static function generatedEncoderDataProvider(): iterable
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

        yield ['dict', Type::dict()];
        yield ['object_dict', Type::dict(Type::object(DummyWithNameAttributes::class))];
        yield ['nullable_object_dict', Type::nullable(Type::dict(Type::object(DummyWithNameAttributes::class)))];

        yield ['iterable', Type::iterable()];
        yield ['object_iterable', Type::iterable(Type::object(ClassicDummy::class))];

        yield ['object', Type::object(DummyWithNameAttributes::class)];
        yield ['nullable_object', Type::nullable(Type::object(DummyWithNameAttributes::class))];
        yield ['object_in_object', Type::object(DummyWithOtherDummies::class)];
        yield ['object_with_normalizer', Type::object(DummyWithNormalizerAttributes::class)];

        yield ['union', Type::union(Type::int(), Type::list(Type::enum(DummyBackedEnum::class)), Type::object(DummyWithNameAttributes::class))];
        yield ['object_with_union', Type::object(DummyWithUnionProperties::class)];
    }

    public function testDoNotSupportIntersectionType()
    {
        $generator = new EncoderGenerator(new PropertyMetadataLoader(TypeResolver::create()), $this->encodersDir);

        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage('"Stringable&Traversable" type is not supported.');

        $generator->generate(Type::intersection(Type::object(\Traversable::class), Type::object(\Stringable::class)));
    }

    public function testDoNotSupportEnumType()
    {
        $generator = new EncoderGenerator(new PropertyMetadataLoader(TypeResolver::create()), $this->encodersDir);

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
                'depth' => 1,
            ])
            ->willReturn([]);

        $generator = new EncoderGenerator($propertyMetadataLoader, $this->encodersDir);
        $generator->generate($type);
    }
}
