<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\DataModel\Decode;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\DataModel\Decode\CollectionNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\CompositeNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\DataModelBuilder;
use Symfony\Component\JsonEncoder\DataModel\Decode\DataModelNodeInterface;
use Symfony\Component\JsonEncoder\DataModel\Decode\ObjectNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\ScalarNode;
use Symfony\Component\JsonEncoder\DataModel\FunctionDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\ScalarDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\VariableDataAccessor;
use Symfony\Component\JsonEncoder\Exception\LogicException;
use Symfony\Component\JsonEncoder\Exception\UnsupportedException;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadata;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithAttributesUsingServices;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithFormatterAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithMethods;
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
    public function testBuildDataModel(DataModelNodeInterface $dataModel, Type $type)
    {
        $typeResolver = TypeResolver::create();
        $dataModelBuilder = new DataModelBuilder(new PropertyMetadataLoader($typeResolver));

        $this->assertEquals($dataModel, $dataModelBuilder->build($type, []));
    }

    /**
     * @return iterable<array{0: DataModelNodeInterface, 1: Type}>
     */
    public static function buildDataModelDataProvider(): iterable
    {
        yield [new ScalarNode(Type::int()), Type::int()];
        yield [new CompositeNode([new ScalarNode(Type::int()), new ScalarNode(Type::null())]), Type::nullable(Type::int())];
        yield [new ScalarNode(Type::builtin(TypeIdentifier::ARRAY)), Type::builtin(TypeIdentifier::ARRAY)];
        yield [new ScalarNode(Type::object()), Type::object()];
        yield [new ScalarNode(Type::null()), Type::null()];

        yield [new CollectionNode(Type::array(Type::string()), new ScalarNode(Type::string())), Type::array(Type::string())];
        yield [new CollectionNode(Type::list(Type::string()), new ScalarNode(Type::string())), Type::list(Type::string())];
        yield [new CollectionNode(Type::dict(Type::string()), new ScalarNode(Type::string())), Type::dict(Type::string())];

        yield [new ObjectNode(Type::object(self::class), []), Type::object(self::class)];

        yield [new CompositeNode([new ScalarNode(Type::int()), new ScalarNode(Type::string())]), Type::union(Type::int(), Type::string())];
        yield [
            new ObjectNode(Type::object(DummyWithUnionProperties::class), [
                'value' => [
                    'name' => 'value',
                    'value' => new CompositeNode([
                        new ScalarNode(Type::enum(DummyBackedEnum::class)),
                        new ScalarNode(Type::null()),
                        new ScalarNode(Type::string()),
                    ]),
                    'accessor' => fn () => false,
                ],
            ]),
            Type::object(DummyWithUnionProperties::class),
        ];
    }

    public function testDoNotSupportIntersectionType()
    {
        $this->expectException(UnsupportedException::class);

        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader());
        $dataModelBuilder->build(Type::intersection(Type::int(), Type::bool()), []);
    }

    public function testDoNotSupportEnumType()
    {
        $this->expectException(UnsupportedException::class);

        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader());
        $dataModelBuilder->build(Type::enum(DummyEnum::class), []);
    }

    public function testAddGhostLeafWhenClassAlreadyGenerated()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata('foo', Type::object(self::class), []),
        ]));

        $this->assertEquals(new ObjectNode(Type::object(self::class), [[
            'name' => 'foo',
            'value' => new ObjectNode(Type::object(self::class), [], true),
            'accessor' => fn () => false,
        ]]), $dataModelBuilder->build(Type::object(self::class), []));
    }

    public function testCallPropertyMetadataLoaderWithProperContext()
    {
        $type = Type::object(self::class, true, [Type::int()]);

        $propertyMetadataLoader = $this->createMock(PropertyMetadataLoaderInterface::class);
        $propertyMetadataLoader->expects($this->once())
            ->method('load')
            ->with(self::class, [], [
                'original_type' => $type,
                'generated_classes' => [(string) $type => true],
            ])
            ->willReturn([]);

        $dataModelBuilder = new DataModelBuilder($propertyMetadataLoader);
        $dataModelBuilder->build($type, []);
    }

    public function testPropertyWithoutFormatter()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata('foo', Type::object(self::class), []),
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), []);
        $accessor = $dataModel->properties[0]['accessor'];

        $this->assertEquals(new VariableDataAccessor('data'), $accessor(new VariableDataAccessor('data')));
    }

    public function testPropertyWithSimpleFormatter()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata('foo', Type::object(self::class), ['strtoupper', DummyWithFormatterAttributes::divideAndCastToInt(...)]),
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), []);
        $accessor = $dataModel->properties[0]['accessor'];

        $this->assertEquals(
            new FunctionDataAccessor(
                sprintf('%s::divideAndCastToInt', DummyWithFormatterAttributes::class),
                [new FunctionDataAccessor('strtoupper', [new VariableDataAccessor('data')])],
            ),
            $accessor(new VariableDataAccessor('data')),
        );
    }

    public function testPropertyWithFormatterWithConfig()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata(
                'foo',
                Type::object(DummyWithFormatterAttributes::class),
                [DummyWithFormatterAttributes::divideAndCastToIntWithConfig(...)],
            ),
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), []);
        $accessor = $dataModel->properties[0]['accessor'];

        $this->assertEquals(
            new FunctionDataAccessor(sprintf('%s::divideAndCastToIntWithConfig', DummyWithFormatterAttributes::class), [
                new VariableDataAccessor('data'),
                new VariableDataAccessor('config'),
            ]),
            $accessor(new VariableDataAccessor('data')),
        );
    }

    public function testPropertyWithFormatterWithRuntimeServices()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata(
                'foo',
                Type::object(DummyWithAttributesUsingServices::class),
                [DummyWithAttributesUsingServices::serviceAndConfig(...)],
            ),
        ]), self::runtimeServices([
            sprintf('%s::serviceAndConfig[service]', DummyWithAttributesUsingServices::class) => 'useless',
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), []);
        $accessor = $dataModel->properties[0]['accessor'];

        $this->assertEquals(
            new FunctionDataAccessor(sprintf('%s::serviceAndConfig', DummyWithAttributesUsingServices::class), [
                new VariableDataAccessor('data'),
                new FunctionDataAccessor(
                    'get',
                    [new ScalarDataAccessor(sprintf('%s::serviceAndConfig[service]', DummyWithAttributesUsingServices::class))],
                    new VariableDataAccessor('services'),
                ),
                new VariableDataAccessor('config'),
            ]),
            $accessor(new VariableDataAccessor('data')),
        );
    }

    public function testPropertyWithConstFormatter()
    {
        $dataModelBuilder = new DataModelBuilder(self::propertyMetadataLoader([
            new PropertyMetadata('foo', Type::object(self::class), [DummyWithMethods::const(...)]),
        ]));

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), []);
        $accessor = $dataModel->properties[0]['accessor'];

        $this->assertEquals(
            new FunctionDataAccessor(sprintf('%s::const', DummyWithMethods::class), []),
            $accessor(new VariableDataAccessor('data')),
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

        /** @var ObjectNode $dataModel */
        $dataModel = $dataModelBuilder->build(Type::object(self::class), []);
        $dataModel->properties[0]['accessor'](new VariableDataAccessor('data'));
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
