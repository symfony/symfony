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
use Symfony\Component\JsonEncoder\DataModel\Decode\DataModelBuilder;
use Symfony\Component\JsonEncoder\Decode\DecodeFrom;
use Symfony\Component\JsonEncoder\Decode\DecoderGenerator;
use Symfony\Component\JsonEncoder\Mapping\Decode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\Decode\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithOtherDummies;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class DecoderGeneratorTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sprintf('%s/symfony_test', sys_get_temp_dir());
        $decoderCacheDir = $this->cacheDir.'/json_encoder/decoder';

        if (is_dir($decoderCacheDir)) {
            array_map('unlink', glob($decoderCacheDir.'/*'));
            rmdir($decoderCacheDir);
        }
    }

    /**
     * @dataProvider generatedDecoderDataProvider
     */
    public function testGeneratedDecoder(string $fixture, Type $type)
    {
        $typeResolver = TypeResolver::create();
        $propertyMetadataLoader = new GenericTypePropertyMetadataLoader(
            new DateTimeTypePropertyMetadataLoader(new AttributePropertyMetadataLoader(
                new PropertyMetadataLoader($typeResolver),
                $typeResolver,
            )),
            new TypeContextFactory(new StringTypeResolver()),
        );

        $generator = new DecoderGenerator(new DataModelBuilder($propertyMetadataLoader), $this->cacheDir);

        $this->assertStringEqualsFile(
            sprintf('%s/Fixtures/decoder/%s.string.php', \dirname(__DIR__), $fixture),
            file_get_contents($generator->generate($type, DecodeFrom::STRING)),
        );

        $this->assertStringEqualsFile(
            sprintf('%s/Fixtures/decoder/%s.stream.php', \dirname(__DIR__), $fixture),
            file_get_contents($generator->generate($type, DecodeFrom::STREAM)),
        );

        $this->assertStringEqualsFile(
            sprintf('%s/Fixtures/decoder/%s.stream.php', \dirname(__DIR__), $fixture),
            file_get_contents($generator->generate($type, DecodeFrom::RESOURCE)),
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
        yield ['iterable_list', Type::iterable(key: Type::int(), asList: true)];

        yield ['dict', Type::dict()];
        yield ['object_dict', Type::dict(Type::object(ClassicDummy::class))];
        yield ['nullable_object_dict', Type::nullable(Type::dict(Type::object(ClassicDummy::class)))];
        yield ['iterable_dict', Type::iterable(key: Type::string())];

        yield ['object', Type::object(ClassicDummy::class)];
        yield ['nullable_object', Type::nullable(Type::object(ClassicDummy::class))];
        yield ['object_in_object', Type::object(DummyWithOtherDummies::class)];
        yield ['object_with_nullable_properties', Type::object(DummyWithNullableProperties::class)];

        yield ['union', Type::union(Type::int(), Type::list(Type::enum(DummyBackedEnum::class)), Type::object(DummyWithNameAttributes::class))];
        yield ['object_with_union', Type::object(DummyWithUnionProperties::class)];
    }
}
