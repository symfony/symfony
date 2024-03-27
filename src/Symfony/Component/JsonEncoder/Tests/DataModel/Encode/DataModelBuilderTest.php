<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\DataModel\Encode;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\DataModel\Encode\CollectionNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\CompositeNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\DataModelBuilder;
use Symfony\Component\JsonEncoder\DataModel\Encode\DataModelNodeInterface;
use Symfony\Component\JsonEncoder\DataModel\Encode\ObjectNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\ScalarNode;
use Symfony\Component\JsonEncoder\DataModel\FunctionDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\PropertyDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\ScalarDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\VariableDataAccessor;
use Symfony\Component\JsonEncoder\Exception\LogicException;
use Symfony\Component\JsonEncoder\Exception\MaxDepthException;
use Symfony\Component\JsonEncoder\Exception\UnsupportedException;
use Symfony\Component\JsonEncoder\Mapping\Encode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadata;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithAttributesUsingServices;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithFormatterAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithMethods;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithOtherDummies;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class DataModelBuilderTest extends TestCase
{
    /**
     * @dataProvider buildDataModelDataProvider
     */
    public function testBuildDataModel(Type $type, DataModelNodeInterface $dataModel)
    {
        $typeResolver = TypeResolver::create();
        $dataModelBuilder = new DataModelBuilder(new PropertyMetadataLoader($typeResolver));

        $this->assertEquals($dataModel, $dataModelBuilder->build($type, new VariableDataAccessor('data'), []));
    }

    /**
     * @return iterable<array{0: Type, 1: DataModelNodeInterface}>
     */
    public static function buildDataModelDataProvider(): iterable
    {
        $accessor = new VariableDataAccessor('data');

        yield [Type::int(), new ScalarNode($accessor, Type::int())];
        yield [Type::nullable(Type::int()), new CompositeNode($accessor, [new ScalarNode($accessor, Type::int()), new ScalarNode($accessor, Type::null())])];
        yield [Type::builtin(TypeIdentifier::ARRAY), new ScalarNode($accessor, Type::builtin(TypeIdentifier::ARRAY))];
        yield [Type::object(), new ScalarNode($accessor, Type::object())];
        yield [Type::enum(DummyBackedEnum::class), new ScalarNode($accessor, Type::enum(DummyBackedEnum::class))];

        yield [Type::array(Type::string()), new CollectionNode($accessor, Type::array(Type::string()), new ScalarNode(new VariableDataAccessor('value_0'), Type::string()))];
        yield [Type::list(Type::string()), new CollectionNode($accessor, Type::list(Type::string()), new ScalarNode(new VariableDataAccessor('value_0'), Type::string()))];
        yield [Type::dict(Type::string()), new CollectionNode($accessor, Type::dict(Type::string()), new ScalarNode(new VariableDataAccessor('value_0'), Type::string()))];

        yield [Type::object(self::class), new ObjectNode($accessor, Type::object(self::class), [], true)];

        yield [Type::union(Type::int(), Type::string()), new CompositeNode($accessor, [new ScalarNode($accessor, Type::int()), new ScalarNode($accessor, Type::string())])];
        yield [
            Type::object(DummyWithUnionProperties::class),
            new ObjectNode($accessor, Type::object(DummyWithUnionProperties::class), [
                'value' => new CompositeNode(new PropertyDataAccessor($accessor, 'value'), [
                    new ScalarNode(new PropertyDataAccessor($accessor, 'value'), Type::enum(DummyBackedEnum::class)),
                    new ScalarNode(new PropertyDataAccessor($accessor, 'value'), Type::null()),
                    new ScalarNode(new PropertyDataAccessor($accessor, 'value'), Type::string()),
                ]),
            ], false),
        ];
    }

    public function testDoNotSupportIntersectionType()
    {
        $this->expectException(UnsupportedException::class);

        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader());
        $dataModelBuilder->build(Type::intersection(Type::int(), Type::bool()), new VariableDataAccessor('data'), []);
    }

    public function testDoNotSupportEnumType()
    {
        $this->expectException(UnsupportedException::class);

        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader());
        $dataModelBuilder->build(Type::enum(DummyEnum::class), new VariableDataAccessor('data'), []);
    }

    /**
     * @dataProvider transformedDataModelDataProvider
     */
    public function testTransformedDataModel(bool $transformed, Type $type)
    {
        $typeResolver = TypeResolver::create();
        $dataModelBuilder = new DataModelBuilder(new AttributePropertyMetadataLoader(new PropertyMetadataLoader($typeResolver), $typeResolver));

        $this->assertEquals(
            $transformed,
            $dataModelBuilder->build($type, new VariableDataAccessor('data'), [])->transformed,
        );
    }

    /**
     * @return iterable<array{0: bool, 1: Type}>
     */
    public static function transformedDataModelDataProvider(): iterable
    {
        yield [false, Type::object(ClassicDummy::class)];
        yield [true, Type::object(DummyWithNameAttributes::class)];
        yield [true, Type::object(DummyWithFormatterAttributes::class)];
        yield [false, Type::object(DummyWithOtherDummies::class)];
    }

    public function testThrowWhenMaxDepthIsReached()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata('foo', Type::object(self::class), []),
        ]));

        $this->expectException(MaxDepthException::class);
        $dataModelBuilder->build(Type::object(self::class), new VariableDataAccessor('data'), []);
    }

    public function testCallPropertyMetadataLoaderWithProperContext()
    {
        $type = Type::object(self::class, true, [Type::int()]);

        $propertyMetadataLoader = $this->createMock(PropertyMetadataLoaderInterface::class);
        $propertyMetadataLoader->expects($this->once())
            ->method('load')
            ->with(self::class, [], [
                'original_type' => $type,
                'depth_counters' => [$type->getClassName() => 1],
            ])
            ->willReturn([]);

        $dataModelBuilder = new DataModelBuilder($propertyMetadataLoader);
        $dataModelBuilder->build($type, new VariableDataAccessor('data'), []);
    }

    public function testPropertyWithSimpleAccessor()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata('foo', Type::int(), []),
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), new VariableDataAccessor('data'), []);

        $this->assertEquals(new PropertyDataAccessor(new VariableDataAccessor('data'), 'foo'), $dataModel->properties[0]->accessor);
    }

    public function testPropertyWithCustomAccessors()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata('foo', Type::int(), ['strtoupper', DummyWithFormatterAttributes::doubleAndCastToString(...)]),
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), new VariableDataAccessor('data'), []);

        $this->assertEquals(
            new FunctionDataAccessor(
                sprintf('%s::doubleAndCastToString', DummyWithFormatterAttributes::class),
                [new FunctionDataAccessor('strtoupper', [new PropertyDataAccessor(new VariableDataAccessor('data'), 'foo')])],
            ),
            $dataModel->properties[0]->accessor,
        );
    }

    public function testPropertyWithAccessorWithConfig()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata(
                'foo',
                Type::int(),
                [DummyWithFormatterAttributes::doubleAndCastToStringWithConfig(...)],
            ),
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), new VariableDataAccessor('data'), []);

        $this->assertEquals(
            new FunctionDataAccessor(sprintf('%s::doubleAndCastToStringWithConfig', DummyWithFormatterAttributes::class), [
                new PropertyDataAccessor(new VariableDataAccessor('data'), 'foo'),
                new VariableDataAccessor('config'),
            ]),
            $dataModel->properties[0]->accessor,
        );
    }

    public function testPropertyWithFormatterWithRuntimeServices()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata(
                'foo',
                Type::int(),
                [DummyWithAttributesUsingServices::serviceAndConfig(...)],
            ),
        ]), self::runtimeServices([
            sprintf('%s::serviceAndConfig[service]', DummyWithAttributesUsingServices::class) => 'useless',
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), new VariableDataAccessor('data'), []);

        $this->assertEquals(
            new FunctionDataAccessor(sprintf('%s::serviceAndConfig', DummyWithAttributesUsingServices::class), [
                new PropertyDataAccessor(new VariableDataAccessor('data'), 'foo'),
                new FunctionDataAccessor(
                    'get',
                    [new ScalarDataAccessor(sprintf('%s::serviceAndConfig[service]', DummyWithAttributesUsingServices::class))],
                    new VariableDataAccessor('services'),
                ),
                new VariableDataAccessor('config'),
            ]),
            $dataModel->properties[0]->accessor,
        );
    }

    public function testPropertyWithConstAccessor()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata('foo', Type::int(), [DummyWithMethods::const(...)]),
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), new VariableDataAccessor('data'), []);

        $this->assertEquals(
            new FunctionDataAccessor(sprintf('%s::const', DummyWithMethods::class), []),
            $dataModel->properties[0]->accessor,
        );
    }

    public function testPropertyWithFormatterWithInvalidArgument()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata(
                'foo',
                Type::object(DummyWithAttributesUsingServices::class),
                [DummyWithAttributesUsingServices::serviceAndConfig(...)],
            ),
        ]));

        $this->expectException(LogicException::class);

        $dataModelBuilder->build(Type::object(self::class), new VariableDataAccessor('data'), []);
    }

    /**
     * @param array<string, PropertyMetadata> $propertiesMetadata
     */
    private static function propertyMetadataLoader(array $propertiesMetadata = []): PropertyMetadataLoaderInterface
    {
        return new class($propertiesMetadata) implements PropertyMetadataLoaderInterface {
            public function __construct(private readonly array $propertiesMetadata)
            {
            }

            public function load(string $className, array $config, array $context): array
            {
                return $this->propertiesMetadata;
            }
        };
    }

    /**
     * @param array<string, mixed> $runtimeServices
     */
    private static function runtimeServices(array $runtimeServices = []): ContainerInterface
    {
        return new class($runtimeServices) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
    }
}
