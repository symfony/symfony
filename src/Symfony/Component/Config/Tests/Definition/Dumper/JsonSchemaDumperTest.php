<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition\Dumper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ExprBuilder;
use Symfony\Component\Config\Definition\Dumper\JsonSchemaDumper;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;
use Symfony\Component\Config\Definition\StringNode;
use Symfony\Component\Config\Definition\VariableNode;
use Symfony\Component\Config\Tests\Fixtures\Configuration\ExampleConfiguration;
use Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum;
use Symfony\Component\Config\Tests\Fixtures\TestEnum;

class JsonSchemaDumperTest extends TestCase
{
    #[DataProvider('provideNodes')]
    public function testJsonSchemaHandlesNodeTypes(NodeInterface $node, array|\stdClass $expected)
    {
        $actual = (new JsonSchemaDumper())->dumpNode($node);
        $actualJson = json_encode($actual, \JSON_THROW_ON_ERROR);
        $expectedJson = json_encode($expected, \JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJson, $actualJson);
    }

    #[DataProvider('provideNodesWithParams')]
    public function testJsonSchemaHandlesNodeTypesWithParams(NodeInterface $node, array|\stdClass $expected)
    {
        $actual = (new JsonSchemaDumper(parameterSchemas: [self::param()]))->dumpNode($node);
        $actualJson = json_encode($actual, \JSON_THROW_ON_ERROR);
        $expectedJson = json_encode($expected, \JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJson, $actualJson);
    }

    private static function param(): array
    {
        return ['$ref' => '#/$defs/types/param'];
    }

    public static function provideNodes(): iterable
    {
        yield [new ArrayNode('node'), ['$ref' => '#/$defs/types/object']];

        yield [new StringNode('node'), ['$ref' => '#/$defs/types/string']];

        yield [new BooleanNode('node'), ['$ref' => '#/$defs/types/boolean']];

        yield [new BooleanNode('node', nullable: true), ['$ref' => '#/$defs/types/boolean_null']];

        yield [new EnumNode('node', values: ['a', 'b']), [
            'enum' => ['a', 'b'],
        ]];
        yield [new EnumNode('node', values: ['a', 'b', null]), [
            'enum' => ['a', 'b', null],
        ]];
        yield [new EnumNode('node', enumFqcn: TestEnum::class), [
            'enum' => [
                '!php/enum Symfony\\Component\\Config\\Tests\\Fixtures\\TestEnum::Foo',
                '!php/enum Symfony\\Component\\Config\\Tests\\Fixtures\\TestEnum::Bar',
                '!php/enum Symfony\\Component\\Config\\Tests\\Fixtures\\TestEnum::Ccc',
                null,
            ],
        ]];
        yield [new EnumNode('node', values: TestEnum::cases()), [
            'enum' => [
                '!php/enum Symfony\\Component\\Config\\Tests\\Fixtures\\TestEnum::Foo',
                '!php/enum Symfony\\Component\\Config\\Tests\\Fixtures\\TestEnum::Bar',
                '!php/enum Symfony\\Component\\Config\\Tests\\Fixtures\\TestEnum::Ccc',
            ],
        ]];
        yield [new EnumNode('node', values: StringBackedTestEnum::cases()), [
            'enum' => [
                '!php/enum Symfony\\Component\\Config\\Tests\\Fixtures\\StringBackedTestEnum::Foo',
                'foo',
                '!php/enum Symfony\\Component\\Config\\Tests\\Fixtures\\StringBackedTestEnum::BarBaz',
                'bar baz',
            ],
        ]];
        yield [new ScalarNode('node'), ['$ref' => '#/$defs/types/scalar']];
        yield [new VariableNode('node'), ['$ref' => '#/$defs/types/variable']];

        yield [new IntegerNode('node'), ['$ref' => '#/$defs/types/integer']];
        yield [new IntegerNode('node', min: 1), ['$ref' => '#/$defs/types/integer', 'minimum' => 1]];
        yield [new IntegerNode('node', max: 10), ['$ref' => '#/$defs/types/integer', 'maximum' => 10]];
        yield [new IntegerNode('node', min: 1, max: 10), ['$ref' => '#/$defs/types/integer', 'minimum' => 1, 'maximum' => 10]];

        yield [new FloatNode('node'), ['$ref' => '#/$defs/types/number']];
        yield [new FloatNode('node', min: 1.1), ['$ref' => '#/$defs/types/number', 'minimum' => 1.1]];
        yield [new FloatNode('node', max: 10.1), ['$ref' => '#/$defs/types/number', 'maximum' => 10.1]];
        yield [new FloatNode('node', min: 1.1, max: 10.1), ['$ref' => '#/$defs/types/number', 'minimum' => 1.1, 'maximum' => 10.1]];

        $prototype = new PrototypedArrayNode('proto');
        $prototype->setPrototype(new StringNode('child'));
        $root = new ArrayNode('root');
        $root->addChild($prototype);
        yield [$prototype, [
            '$ref' => '#/$defs/types/array',
            'items' => ['$ref' => '#/$defs/types/string'],
        ]];

        $prototype = new PrototypedArrayNode('proto');
        $prototype->setPrototype(new StringNode('child'));
        $prototype->setKeyAttribute('name');
        $root = new ArrayNode('root');
        $root->addChild($prototype);
        yield 'key-attribute map with scalar prototype' => [$prototype, [
            '$ref' => '#/$defs/types/object',
            'additionalProperties' => ['$ref' => '#/$defs/types/string'],
        ]];

        $mapWithNormalization = (new ArrayNodeDefinition('proto'))
            ->useAttributeAsKey('name')
            ->stringPrototype()->end()
            ->beforeNormalization()->ifArray()->then(static fn ($v) => $v)->end()
            ->getNode();
        yield 'key-attribute map with a normalization closure also accepts a list' => [$mapWithNormalization, [
            'anyOf' => [
                [
                    '$ref' => '#/$defs/types/object_null',
                    'additionalProperties' => ['$ref' => '#/$defs/types/string_null'],
                ],
                [
                    '$ref' => '#/$defs/types/array_null',
                    'items' => ['$ref' => '#/$defs/types/string_null'],
                ],
            ],
        ]];

        // A prototyped list with a null default is nullable, so it uses the array_null ref.
        $listWithNullDefault = (new ArrayNodeDefinition('proto'))->defaultNull()->stringPrototype()->end()->getNode();
        yield 'array list with null default uses the nullable ref' => [$listWithNullDefault, [
            '$ref' => '#/$defs/types/array_null',
            'items' => ['$ref' => '#/$defs/types/string_null'],
            'default' => null,
        ]];

        // A prototyped map with a null default is nullable, so it uses the object_null ref.
        $mapWithNullDefault = (new ArrayNodeDefinition('proto'))->defaultNull()->useAttributeAsKey('name')->stringPrototype()->end()->getNode();
        yield 'array map with null default uses the nullable ref' => [$mapWithNullDefault, [
            '$ref' => '#/$defs/types/object_null',
            'additionalProperties' => ['$ref' => '#/$defs/types/string_null'],
            'default' => null,
        ]];

        $child = new BooleanNode('node');
        $child->setRequired(true);
        $root = new ArrayNode('root');
        $root->addChild($child);
        yield [$root, [
            '$ref' => '#/$defs/types/object',
            'properties' => ['node' => ['$ref' => '#/$defs/types/boolean']],
            'required' => ['node'],
            'additionalProperties' => false,
        ]];

        $child = new BooleanNode('node');
        $child->setDeprecated('vendor/package', '1.0', 'The "%path%" option is deprecated.');
        $child->setDefaultValue(true);
        $child->setInfo('This is a boolean node.');
        $root = new ArrayNode('root');
        $root->addChild($child);
        yield [$root, [
            '$ref' => '#/$defs/types/object',
            'properties' => [
                'node' => [
                    '$ref' => '#/$defs/types/boolean',
                    'default' => true,
                    'deprecated' => true,
                    'description' => "Deprecated since vendor/package 1.0: The \"node\" option is deprecated.\n\nThis is a boolean node.",
                ],
            ],
            'additionalProperties' => false,
        ]];

        $root = new ArrayNode('root');
        $child = new ArrayNode('child');
        $root->addChild($child);
        yield [$root, [
            '$ref' => '#/$defs/types/object',
            'properties' => [
                'child' => ['$ref' => '#/$defs/types/object'],
            ],
            'additionalProperties' => false,
        ]];

        $node = new ArrayNodeDefinition('foo');
        $node->canBeEnabled()->children()->booleanNode('bar')->end();
        yield [$node->getNode(), [
            'anyOf' => [
                [
                    '$ref' => '#/$defs/types/object_null',
                    'properties' => [
                        'enabled' => ['$ref' => '#/$defs/types/boolean', 'default' => false],
                        'bar' => ['$ref' => '#/$defs/types/boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                [
                    'type' => ['boolean'],
                ],
            ],
            'default' => ['enabled' => false],
        ]];

        $node = new ArrayNodeDefinition('foo');
        $node->canBeDisabled()->children()->booleanNode('bar')->end();
        yield [$node->getNode(), [
            'anyOf' => [
                [
                    '$ref' => '#/$defs/types/object_null',
                    'properties' => [
                        'enabled' => ['$ref' => '#/$defs/types/boolean', 'default' => true],
                        'bar' => ['$ref' => '#/$defs/types/boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                [
                    'type' => ['boolean'],
                ],
            ],
            'default' => ['enabled' => true],
        ]];

        $node = new ArrayNodeDefinition('foo');
        $node->useAttributeAsKey('name')
            ->prototype('array')
            ->acceptAndWrap(['string', 'int'], 'name')
            ->children()
                ->stringNode('name')->end()
                ->floatNode('price')->isRequired()->end()
        ;
        yield [$node->getNode(), [
            '$ref' => '#/$defs/types/object_null',
            'additionalProperties' => [
                'anyOf' => [
                    [
                        '$ref' => '#/$defs/types/object_null',
                        'properties' => [
                            'name' => ['$ref' => '#/$defs/types/string_null'],
                            'price' => ['$ref' => '#/$defs/types/number'],
                        ],
                        'required' => ['price'],
                        'additionalProperties' => false,
                    ],
                    [
                        'type' => ['string', 'integer'],
                    ],
                ],
            ],
        ]];
    }

    public static function provideNodesWithParams(): iterable
    {
        yield [new BooleanNode('node'), ['$ref' => '#/$defs/types/boolean']];
        yield [new BooleanNode('node', nullable: true), ['$ref' => '#/$defs/types/boolean_null']];
        yield [new IntegerNode('node'), ['$ref' => '#/$defs/types/integer']];
        yield [new IntegerNode('node', min: 1), ['$ref' => '#/$defs/types/integer', 'minimum' => 1]];
        yield [new FloatNode('node'), ['$ref' => '#/$defs/types/number']];
        yield [new EnumNode('node', values: ['a', 'b']), ['anyOf' => [['enum' => ['a', 'b']], self::param()]]];

        // When normalizedTypes are set on an EnumNode that already has anyOf from parameterSchemas,
        // the result must be a flat anyOf (no nesting).
        $node = new EnumNode('node', values: ['a', 'b']);
        $node->setNormalizedTypes([ExprBuilder::TYPE_STRING]);
        yield [$node, ['anyOf' => [['enum' => ['a', 'b']], self::param(), ['type' => ['string']]]]];
    }

    public function testGetAllDefs()
    {
        $allDefs = (new JsonSchemaDumper())->getAllDefs();
        $this->assertArrayHasKey('types', $allDefs);
        $types = $allDefs['types'];
        $this->assertArrayHasKey('string', $types);
        $this->assertArrayHasKey('string_null', $types);
        $this->assertArrayHasKey('boolean', $types);
        $this->assertArrayHasKey('boolean_null', $types);
        $this->assertArrayHasKey('integer', $types);
        $this->assertArrayHasKey('integer_null', $types);
        $this->assertArrayHasKey('number', $types);
        $this->assertArrayHasKey('number_null', $types);
        $this->assertArrayHasKey('scalar', $types);
        $this->assertArrayHasKey('variable', $types);
        $this->assertArrayHasKey('array', $types);
        $this->assertArrayHasKey('array_null', $types);
        $this->assertArrayHasKey('object', $types);
        $this->assertArrayHasKey('object_null', $types);

        // Without parameterSchemas: simple type schemas
        $this->assertSame(['type' => 'string'], $types['string']);
        $this->assertSame(['type' => 'boolean'], $types['boolean']);
        $this->assertSame(['type' => 'integer'], $types['integer']);

        // With parameterSchemas: anyOf with param
        $param = self::param();
        $types = (new JsonSchemaDumper(parameterSchemas: [$param]))->getAllDefs()['types'];
        $this->assertSame(['anyOf' => [['type' => 'boolean'], $param]], $types['boolean']);
        $this->assertSame(['anyOf' => [['type' => 'integer'], $param]], $types['integer']);
    }

    public function testExampleConfiguration()
    {
        $configuration = new ExampleConfiguration();

        $root = new ArrayNode(null);
        $root->addChild($configuration->getConfigTreeBuilder()->buildTree());

        $schema = (new JsonSchemaDumper())->dump($root, [
            'title' => 'Example Configuration Schema',
        ]);
        $jsonSchema = json_encode($schema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";

        if ($_ENV['TEST_GENERATE_FIXTURES'] ?? false) {
            file_put_contents(__DIR__.'/../../../Tests/Fixtures/Configuration/ExampleConfiguration.schema.json', $jsonSchema);
            $this->markTestIncomplete('TEST_GENERATE_FIXTURES is set');
        }

        $this->assertJsonStringEqualsJsonFile(__DIR__.'/../../../Tests/Fixtures/Configuration/ExampleConfiguration.schema.json', $jsonSchema);
    }
}
