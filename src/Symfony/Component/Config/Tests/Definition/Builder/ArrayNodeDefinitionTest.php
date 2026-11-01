<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition\Builder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidDefinitionException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

class ArrayNodeDefinitionTest extends TestCase
{
    public function testAppendingSomeNode()
    {
        $parent = new ArrayNodeDefinition('root');
        $child = new ScalarNodeDefinition('child');

        $parent
            ->children()
                ->scalarNode('foo')->end()
                ->scalarNode('bar')->end()
            ->end()
            ->append($child);

        $this->assertCount(3, $this->getField($parent, 'children'));
        $this->assertContains($child, $this->getField($parent, 'children'));
    }

    #[DataProvider('providePrototypeNodeSpecificCalls')]
    public function testPrototypeNodeSpecificOption(string $method, array $args)
    {
        $this->expectException(InvalidDefinitionException::class);
        $node = new ArrayNodeDefinition('root');

        $node->{$method}(...$args);

        $node->getNode();
    }

    public static function providePrototypeNodeSpecificCalls(): array
    {
        return [
            ['defaultValue', [[]]],
            ['addDefaultChildrenIfNoneSet', []],
            ['requiresAtLeastOneElement', []],
            ['cannotBeEmpty', []],
            ['useAttributeAsKey', ['foo']],
        ];
    }

    public function testConcreteNodeSpecificOption()
    {
        $this->expectException(InvalidDefinitionException::class);
        $node = new ArrayNodeDefinition('root');
        $node
            ->addDefaultsIfNotSet()
            ->prototype('array')
        ;
        $node->getNode();
    }

    public function testPrototypeNodesCantHaveADefaultValueWhenUsingDefaultChildren()
    {
        $this->expectException(InvalidDefinitionException::class);
        $node = new ArrayNodeDefinition('root');
        $node
            ->defaultValue([])
            ->addDefaultChildrenIfNoneSet('foo')
            ->prototype('array')
        ;
        $node->getNode();
    }

    public function testPrototypedArrayNodeDefaultWhenUsingDefaultChildren()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->addDefaultChildrenIfNoneSet()
            ->prototype('array')
        ;
        $tree = $node->getNode();
        $this->assertEquals([[]], $tree->getDefaultValue());
    }

    #[DataProvider('providePrototypedArrayNodeDefaults')]
    public function testPrototypedArrayNodeDefault(int|array|string|null $args, bool $shouldThrowWhenUsingAttrAsKey, bool $shouldThrowWhenNotUsingAttrAsKey, array $defaults)
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->addDefaultChildrenIfNoneSet($args)
            ->prototype('array')
        ;

        try {
            $tree = $node->getNode();
            $this->assertFalse($shouldThrowWhenNotUsingAttrAsKey);
            $this->assertEquals($defaults, $tree->getDefaultValue());
        } catch (InvalidDefinitionException $e) {
            $this->assertTrue($shouldThrowWhenNotUsingAttrAsKey);
        }

        $node = new ArrayNodeDefinition('root');
        $node
            ->useAttributeAsKey('attr')
            ->addDefaultChildrenIfNoneSet($args)
            ->prototype('array')
        ;

        try {
            $tree = $node->getNode();
            $this->assertFalse($shouldThrowWhenUsingAttrAsKey);
            $this->assertEquals($defaults, $tree->getDefaultValue());
        } catch (InvalidDefinitionException $e) {
            $this->assertTrue($shouldThrowWhenUsingAttrAsKey);
        }
    }

    public static function providePrototypedArrayNodeDefaults(): array
    {
        return [
            [null, true, false, [[]]],
            [2, true, false, [[], []]],
            ['2', false, true, ['2' => []]],
            ['foo', false, true, ['foo' => []]],
            [['foo'], false, true, ['foo' => []]],
            [['foo', 'bar'], false, true, ['foo' => [], 'bar' => []]],
        ];
    }

    public function testNestedPrototypedArrayNodes()
    {
        $nodeDefinition = new ArrayNodeDefinition('root');
        $nodeDefinition
            ->addDefaultChildrenIfNoneSet()
            ->prototype('array')
                  ->prototype('array')
        ;
        $node = $nodeDefinition->getNode();

        $this->assertInstanceOf(PrototypedArrayNode::class, $node);
        $this->assertInstanceOf(PrototypedArrayNode::class, $node->getPrototype());
    }

    public function testEnabledNodeDefaults()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('foo')->defaultValue('bar')->end()
        ;

        $this->assertEquals(['enabled' => false, 'foo' => 'bar'], $node->getNode()->getDefaultValue());
    }

    #[DataProvider('getEnableableNodeFixtures')]
    public function testTrueEnableEnabledNode(array $expected, array $config, string $message)
    {
        $processor = new Processor();
        $node = new ArrayNodeDefinition('root');
        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('foo')->defaultValue('bar')->end()
        ;

        $this->assertEquals(
            $expected,
            $processor->process($node->getNode(), $config),
            $message
        );
    }

    public function testCanBeEnabledWithInfo()
    {
        $node = new ArrayNodeDefinition('root');
        $node->canBeEnabled('Some info about disabling this node');

        $child = $this->getField($node, 'children')['enabled'];

        $this->assertEquals('Some info about disabling this node', $this->getField($child, 'attributes')['info']);
    }

    public function testCanBeDisabled()
    {
        $node = new ArrayNodeDefinition('root');
        $node->canBeDisabled();

        $this->assertTrue($this->getField($node, 'addDefaults'));
        $this->assertEquals(['enabled' => false], $this->getField($node, 'falseEquivalent'));
        $this->assertEquals(['enabled' => true], $this->getField($node, 'trueEquivalent'));
        $this->assertEquals(['enabled' => true], $this->getField($node, 'nullEquivalent'));

        $nodeChildren = $this->getField($node, 'children');
        $this->assertArrayHasKey('enabled', $nodeChildren);

        $enabledNode = $nodeChildren['enabled'];
        $this->assertTrue($this->getField($enabledNode, 'default'));
        $this->assertTrue($this->getField($enabledNode, 'defaultValue'));
    }

    public function testCanBeDisabledWithInfo()
    {
        $node = new ArrayNodeDefinition('root');
        $node->canBeDisabled('Some info about disabling this node');

        $child = $this->getField($node, 'children')['enabled'];

        $this->assertEquals('Some info about disabling this node', $this->getField($child, 'attributes')['info']);
    }

    public function testIgnoreExtraKeys()
    {
        $node = new ArrayNodeDefinition('root');

        $this->assertFalse($this->getField($node, 'ignoreExtraKeys'));

        $result = $node->ignoreExtraKeys();

        $this->assertEquals($node, $result);
        $this->assertTrue($this->getField($node, 'ignoreExtraKeys'));
    }

    public function testNormalizeKeys()
    {
        $node = new ArrayNodeDefinition('root');

        $this->assertTrue($this->getField($node, 'normalizeKeys'));

        $result = $node->normalizeKeys(false);

        $this->assertEquals($node, $result);
        $this->assertFalse($this->getField($node, 'normalizeKeys'));
    }

    #[DataProvider('provideRecursiveKeyNormalization')]
    public function testNormalizeKeysRecursively(bool $normalizeKeys, array $expected)
    {
        $root = new ArrayNodeDefinition('root');
        $nested = new ArrayNodeDefinition('nested');
        $deep = new ArrayNodeDefinition('deep');
        $root->ignoreExtraKeys(false)->append($nested);
        $nested->ignoreExtraKeys(false)->append($deep);
        $deep->ignoreExtraKeys(false);

        $this->assertSame($root, $root->normalizeKeysRecursively($normalizeKeys));
        $this->assertSame($expected, (new Processor())->process($root->getNode(), [[
            'root-key' => 1,
            'nested' => ['child-key' => 2, 'deep' => ['leaf-key' => 3]],
        ]]));
    }

    public static function provideRecursiveKeyNormalization(): iterable
    {
        yield 'disabled' => [false, ['root-key' => 1, 'nested' => ['child-key' => 2, 'deep' => ['leaf-key' => 3]]]];
        yield 'enabled' => [true, ['root_key' => 1, 'nested' => ['child_key' => 2, 'deep' => ['leaf_key' => 3]]]];
    }

    public function testRecursiveKeyNormalizationAppliesToPrototypes()
    {
        $root = new ArrayNodeDefinition('root');
        $root->normalizeKeysRecursively(false);
        $items = new ArrayNodeDefinition('items');
        $item = $items->useAttributeAsKey('name')->arrayPrototype()->ignoreExtraKeys(false);
        $data = new ArrayNodeDefinition('data');
        $data->scalarPrototype();
        $item->append($data);
        $root->append($items);
        $input = ['items' => ['first-item' => ['item-key' => 1, 'data' => ['data-key' => 2]]]];

        $this->assertSame($input, (new Processor())->process($root->getNode(), [$input]));
    }

    #[DataProvider('provideLocalKeyNormalizationOverrides')]
    public function testLocalKeyNormalizationOverridesRecursiveDefault(bool $recursive, bool $local, array $expected)
    {
        $root = new ArrayNodeDefinition('root');
        $child = new ArrayNodeDefinition('child');
        $child->normalizeKeys($local)->ignoreExtraKeys(false);
        $deep = new ArrayNodeDefinition('deep');
        $deep->ignoreExtraKeys(false);
        $child->append($deep);
        $root->append($child)->normalizeKeysRecursively($recursive);

        $this->assertSame($expected, (new Processor())->process($root->getNode(), [[
            'child' => ['child-key' => 1, 'deep' => ['deep-key' => 2]],
        ]]));
    }

    public static function provideLocalKeyNormalizationOverrides(): iterable
    {
        yield 'enable locally' => [false, true, ['child' => ['child_key' => 1, 'deep' => ['deep-key' => 2]]]];
        yield 'disable locally' => [true, false, ['child' => ['child-key' => 1, 'deep' => ['deep_key' => 2]]]];
    }

    public function testChildRecursiveKeyNormalizationOverridesParentDefault()
    {
        $root = new ArrayNodeDefinition('root');
        $child = new ArrayNodeDefinition('child');
        $child->normalizeKeysRecursively(true)->ignoreExtraKeys(false);
        $deep = new ArrayNodeDefinition('deep');
        $deep->ignoreExtraKeys(false);
        $child->append($deep);
        $root->append($child)->normalizeKeysRecursively(false)->ignoreExtraKeys(false);

        $this->assertSame(['root-key' => 1, 'child' => ['child_key' => 2, 'deep' => ['deep_key' => 3]]], (new Processor())->process($root->getNode(), [[
            'root-key' => 1, 'child' => ['child-key' => 2, 'deep' => ['deep-key' => 3]],
        ]]));
    }

    public function testRecursiveKeyNormalizationDoesNotPersistOnReusedDefinition()
    {
        $child = new ArrayNodeDefinition('child');
        $child->ignoreExtraKeys(false);
        $root = new ArrayNodeDefinition('root');
        $root->append($child)->normalizeKeysRecursively(false);
        $processor = new Processor();

        $this->assertSame(['child' => ['test-key' => 1]], $processor->process($root->getNode(), [['child' => ['test-key' => 1]]]));
        $this->assertSame(['test_key' => 1], $processor->process($child->getNode(true), [['test-key' => 1]]));
        $root->normalizeKeysRecursively(true);
        $this->assertSame(['child' => ['test_key' => 1]], $processor->process($root->getNode(), [['child' => ['test-key' => 1]]]));
    }

    public function testNormalizeKeysRemainsLocal()
    {
        $root = new ArrayNodeDefinition('root');
        $root->normalizeKeys(false)->ignoreExtraKeys(false);
        $child = new ArrayNodeDefinition('child');
        $child->ignoreExtraKeys(false);
        $root->append($child);

        $this->assertSame(['root-key' => 1, 'child' => ['child_key' => 2]], (new Processor())->process($root->getNode(), [[
            'root-key' => 1, 'child' => ['child-key' => 2],
        ]]));
    }

    public function testUnsetChild()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->children()
                ->scalarNode('value')
                    ->beforeNormalization()
                        ->ifTrue(static fn ($value) => !$value)
                        ->thenUnset()
                    ->end()
                ->end()
            ->end()
        ;

        $this->assertSame([], $node->getNode()->normalize(['value' => null]));
    }

    public function testPrototypeVariable()
    {
        $node = new ArrayNodeDefinition('root');
        $this->assertEquals($node->prototype('variable'), $node->variablePrototype());
    }

    public function testPrototypeScalar()
    {
        $node = new ArrayNodeDefinition('root');
        $this->assertEquals($node->prototype('scalar'), $node->scalarPrototype());
    }

    public function testPrototypeBoolean()
    {
        $node = new ArrayNodeDefinition('root');
        $this->assertEquals($node->prototype('boolean'), $node->booleanPrototype());
    }

    public function testPrototypeString()
    {
        $node = new ArrayNodeDefinition('root');
        $this->assertEquals($node->prototype('string'), $node->stringPrototype());
    }

    public function testPrototypeInteger()
    {
        $node = new ArrayNodeDefinition('root');
        $this->assertEquals($node->prototype('integer'), $node->integerPrototype());
    }

    public function testPrototypeFloat()
    {
        $node = new ArrayNodeDefinition('root');
        $this->assertEquals($node->prototype('float'), $node->floatPrototype());
    }

    public function testPrototypeArray()
    {
        $node = new ArrayNodeDefinition('root');
        $this->assertEquals($node->prototype('array'), $node->arrayPrototype());
    }

    public function testPrototypedArrayAllowsNullDefault()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->defaultValue(null)
            ->prototype('array')
        ;

        $tree = $node->getNode();
        $this->assertNull($tree->getDefaultValue());
    }

    public function testPrototypedArrayRejectsNonArrayDefaultValue()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->defaultValue('not-an-array')
            ->prototype('array')
        ;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the default value of an array node has to be an array or null');
        $node->getNode();
    }

    public function testPrototypeEnum()
    {
        $node = new ArrayNodeDefinition('root');
        $this->assertEquals($node->prototype('enum'), $node->enumPrototype());
    }

    public static function getEnableableNodeFixtures(): array
    {
        return [
            [['enabled' => true, 'foo' => 'bar'], [true], 'true enables an enableable node'],
            [['enabled' => true, 'foo' => 'bar'], [null], 'null enables an enableable node'],
            [['enabled' => true, 'foo' => 'bar'], [['enabled' => true]], 'An enableable node can be enabled'],
            [['enabled' => true, 'foo' => 'baz'], [['foo' => 'baz']], 'any configuration enables an enableable node'],
            [['enabled' => false, 'foo' => 'baz'], [['foo' => 'baz', 'enabled' => false]], 'An enableable node can be disabled'],
            [['enabled' => false, 'foo' => 'bar'], [false], 'false disables an enableable node'],
        ];
    }

    #[DataProvider('provideEnabledStateAfterMerging')]
    public function testEnabledStateIsPreservedAcrossMergedConfigs(array $firstConfig, array $expected, callable $nodeFactory)
    {
        $processor = new Processor();
        $node = $nodeFactory();

        $this->assertSame(
            $expected,
            $processor->process($node->getNode(), [
                $firstConfig,
                ['foo' => 'baz'],
            ])
        );
    }

    public static function provideEnabledStateAfterMerging(): array
    {
        $factory = static function (callable $builder) {
            $node = new ArrayNodeDefinition('root');
            $builder($node)
                ->children()
                    ->scalarNode('foo')->defaultValue('bar')->end()
                ->end()
            ;

            return $node;
        };

        return [
            'canBeEnabled keeps explicit true' => [
                ['enabled' => true],
                ['enabled' => true, 'foo' => 'baz'],
                static fn () => $factory(static fn ($node) => $node->canBeEnabled()),
            ],
            'canBeDisabled keeps explicit false' => [
                ['enabled' => false],
                ['enabled' => false, 'foo' => 'baz'],
                static fn () => $factory(static fn ($node) => $node->canBeDisabled()),
            ],
            'canBeDisabled keeps explicit true' => [
                ['enabled' => true],
                ['enabled' => true, 'foo' => 'baz'],
                static fn () => $factory(static fn ($node) => $node->canBeDisabled()),
            ],
        ];
    }

    public function testRequiresAtLeastOneElement()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->requiresAtLeastOneElement()
            ->integerPrototype();

        $node->getNode()->finalize([1]);

        $this->addToAssertionCount(1);
    }

    public function testCannotBeEmpty()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "root" should have at least 1 element(s) defined.');
        $node = new ArrayNodeDefinition('root');
        $node
            ->cannotBeEmpty()
            ->integerPrototype();

        $node->getNode()->finalize([]);
    }

    public function testSetDeprecated()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->children()
                ->arrayNode('foo')->setDeprecated('vendor/package', '1.1', 'The "%path%" node is deprecated.')->end()
            ->end()
        ;
        $deprecatedNode = $node->getNode()->getChildren()['foo'];

        $this->assertTrue($deprecatedNode->isDeprecated());
        $deprecation = $deprecatedNode->getDeprecation($deprecatedNode->getName(), $deprecatedNode->getPath());
        $this->assertSame('The "root.foo" node is deprecated.', $deprecation['message']);
        $this->assertSame('vendor/package', $deprecation['package']);
        $this->assertSame('1.1', $deprecation['version']);
    }

    public function testCannotBeEmptyOnConcreteNode()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('->cannotBeEmpty() is not applicable to concrete nodes at path "root"');
        $node = new ArrayNodeDefinition('root');
        $node->cannotBeEmpty();

        $node->getNode()->finalize([]);
    }

    #[DataProvider('provideArraysWrappedIntoAChild')]
    public function testAcceptAndWrapArraysIntoAChild(array $config, array $expected)
    {
        $node = (new ArrayNodeDefinition('root'))
            ->canBeDisabled()
            ->acceptAndWrap(['array'], 'items')
            ->children()
                ->arrayNode('options', 'option')->scalarPrototype()->end()->end()
                ->arrayNode('items', 'item')->useAttributeAsKey('name')->scalarPrototype()->end()->end()
            ->end()
        ;

        $this->assertEquals($expected, (new Processor())->process($node->getNode(), [$config]));
    }

    public static function provideArraysWrappedIntoAChild(): iterable
    {
        yield 'the entries go into the child' => [['foo' => 'a', 'bar' => 'b'], ['enabled' => true, 'options' => [], 'items' => ['foo' => 'a', 'bar' => 'b']]];
        yield 'the other children stay' => [['enabled' => false, 'options' => ['x'], 'foo' => 'a'], ['enabled' => false, 'options' => ['x'], 'items' => ['foo' => 'a']]];
        yield 'the singular of another child stays' => [['option' => 'x', 'foo' => 'a'], ['enabled' => true, 'options' => ['x'], 'items' => ['foo' => 'a']]];
        yield 'an array setting the child is not wrapped' => [['items' => ['foo' => 'a'], 'options' => ['x']], ['enabled' => true, 'options' => ['x'], 'items' => ['foo' => 'a']]];
        yield 'an array setting the singular of the child is not wrapped' => [['item' => [['name' => 'foo', 'value' => 'a']]], ['enabled' => true, 'options' => [], 'items' => ['foo' => 'a']]];
    }

    public function testAcceptAndWrapArraysRequiresTheChildToWrapThemInto()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Wrapping arrays requires the name of the child to wrap them into.');

        (new ArrayNodeDefinition('root'))->acceptAndWrap(['array']);
    }

    public function testAcceptAndWrapArraysIntoAMissingChild()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('->acceptAndWrap() cannot wrap arrays into "items" as it is not a child of the node at path "root".');

        (new ArrayNodeDefinition('root'))
            ->acceptAndWrap(['array'], 'items')
            ->children()
                ->scalarNode('item')->end()
            ->end()
            ->getNode();
    }

    #[DataProvider('provideMapsWrappedIntoTheItemsOfAList')]
    public function testAcceptAndWrapMapsIntoTheItemsOfAList(array $config, array $expected)
    {
        $node = (new ArrayNodeDefinition('root'))
            ->acceptAndWrap(['array'], 'name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->isRequired()->end()
                    ->scalarNode('label')->end()
                ->end()
            ->end()
        ;

        $this->assertEquals($expected, (new Processor())->process($node->getNode(), [$config]));
    }

    public static function provideMapsWrappedIntoTheItemsOfAList(): iterable
    {
        yield 'the keys of a map go into the items' => [['foo' => ['label' => 'Foo'], 'bar' => []], ['foo' => ['name' => 'foo', 'label' => 'Foo'], 'bar' => ['name' => 'bar']]];
        yield 'an item setting the child keeps it' => [['foo' => ['name' => 'bar']], ['foo' => ['name' => 'bar']]];
        yield 'a list is not changed' => [[['name' => 'foo'], ['name' => 'foo', 'label' => 'Foo']], [['name' => 'foo'], ['name' => 'foo', 'label' => 'Foo']]];
    }

    public function testAcceptAndWrapArraysIntoAMissingChildOfTheItemsOfAList()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('->acceptAndWrap() cannot wrap arrays into "name" as it is not a child of the items of the node at path "root".');

        (new ArrayNodeDefinition('root'))
            ->acceptAndWrap(['array'], 'name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('label')->end()
                ->end()
            ->end()
            ->getNode();
    }

    public function testAcceptAndWrapArraysIsNotApplicableToMaps()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('->acceptAndWrap() cannot wrap arrays into a prototype node that uses ->useAttributeAsKey() at path "root".');

        (new ArrayNodeDefinition('root'))
            ->acceptAndWrap(['array'], 'items')
            ->useAttributeAsKey('name')
            ->scalarPrototype()->end()
            ->getNode();
    }

    public function testAcceptAndWrapArraysKeepsTheTypesDeclaredByTheClosures()
    {
        $node = (new ArrayNodeDefinition('root'))
            ->acceptAndWrap(['array'], 'name')
            ->beforeNormalization()->ifString()->then(static fn ($v) => [['name' => $v]])->end()
            ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->end()
                ->end()
            ->end()
        ;

        $this->assertSame(['string', 'array'], $node->getNode()->getNormalizedTypes());
    }

    public function testAcceptAndWrapDoesNotHideTheClosuresThatAcceptAnyValue()
    {
        $node = (new ArrayNodeDefinition('root'))
            ->acceptAndWrap(['string'])
            ->beforeNormalization()->ifNull()->then(static fn () => ['null'])->end()
            ->beforeNormalization()->ifTrue(static fn ($v) => \is_array($v) && isset($v['id']))->then(static fn ($v) => [$v])->end()
            ->variablePrototype()->end()
        ;

        $this->assertSame(['string', 'any', 'array'], $node->getNode()->getNormalizedTypes());
    }

    public function testAcceptElementsAtParentLevel()
    {
        $node = (new ArrayNodeDefinition('root'))
            ->beforeNormalization()->ifArray()->then(static fn ($v) => $v)->end()
            ->children()
                ->scalarNode('name')->end()
            ->end()
            ->append(self::createElementsAtParentLevelNode())
        ;

        $stores = $node->getNode()->getChildren()['stores'];

        $this->assertInstanceOf(PrototypedArrayNode::class, $stores);
        $this->assertTrue($stores->getAttribute('elements_at_parent_level'));
    }

    public function testAcceptElementsAtParentLevelRequiresANormalizationClosureOnTheParent()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('The node "root" accepts the elements of "stores" at its level but has no beforeNormalization() closure to rewrite them.');

        (new ArrayNodeDefinition('root'))
            ->children()
                ->scalarNode('name')->end()
            ->end()
            ->append(self::createElementsAtParentLevelNode())
            ->getNode();
    }

    public function testAcceptElementsAtParentLevelRejectsAKeyDeclaredByTheParentAndTheElements()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('The node "root" and the elements of "stores" both declare the key(s) "host", so they cannot be told apart when they are given at the level of the node.');

        (new ArrayNodeDefinition('root'))
            ->beforeNormalization()->ifArray()->then(static fn ($v) => $v)->end()
            ->children()
                ->scalarNode('host')->end()
            ->end()
            ->append(self::createElementsAtParentLevelNode())
            ->getNode();
    }

    public function testAcceptElementsAtParentLevelRejectsAKeyDeclaredByTheParentAndTheSingularOfTheElements()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('The node "root" and the elements of "stores" both declare the key(s) "address", so they cannot be told apart when they are given at the level of the node.');

        $stores = new ArrayNodeDefinition('stores');
        $stores
            ->useAttributeAsKey('name')
            ->acceptElementsAtParentLevel()
            ->prototype('array')
                ->fixXmlConfig('address')
                ->children()
                    ->scalarNode('addresses')->end()
                ->end()
            ->end()
        ;

        (new ArrayNodeDefinition('root'))
            ->beforeNormalization()->ifArray()->then(static fn ($v) => $v)->end()
            ->children()
                ->scalarNode('address')->end()
            ->end()
            ->append($stores)
            ->getNode();
    }

    public function testAcceptElementsAtParentLevelIsNotApplicableToConcreteNodes()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('->acceptElementsAtParentLevel() is not applicable to concrete nodes at path "root".');

        (new ArrayNodeDefinition('root'))
            ->acceptElementsAtParentLevel()
            ->children()
                ->scalarNode('name')->end()
            ->end()
            ->getNode();
    }

    #[DataProvider('provideNodesWithoutAnArrayPrototypeWithChildren')]
    public function testAcceptElementsAtParentLevelRequiresAnArrayPrototypeWithChildren(ArrayNodeDefinition $node)
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('->acceptElementsAtParentLevel() requires the prototype of the node at path "root" to be an array with children.');

        $node->getNode();
    }

    public static function provideNodesWithoutAnArrayPrototypeWithChildren(): iterable
    {
        yield 'a scalar prototype' => [(new ArrayNodeDefinition('root'))->acceptElementsAtParentLevel()->useAttributeAsKey('name')->scalarPrototype()->end()];
        yield 'a prototyped array prototype' => [(new ArrayNodeDefinition('root'))->acceptElementsAtParentLevel()->useAttributeAsKey('name')->arrayPrototype()->scalarPrototype()->end()->end()];
        yield 'an array prototype without children' => [(new ArrayNodeDefinition('root'))->acceptElementsAtParentLevel()->useAttributeAsKey('name')->arrayPrototype()->end()];
    }

    public function testAcceptElementsAtParentLevelRejectsSeveralChildrenOfTheSameNode()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Only one child of the node "root" can declare ->acceptElementsAtParentLevel(), found "stores", "caches".');

        $caches = new ArrayNodeDefinition('caches');
        $caches
            ->acceptElementsAtParentLevel()
            ->arrayPrototype()
                ->children()
                    ->integerNode('ttl')->end()
                ->end()
            ->end()
        ;

        (new ArrayNodeDefinition('root'))
            ->beforeNormalization()->ifArray()->then(static fn ($v) => $v)->end()
            ->append(self::createElementsAtParentLevelNode())
            ->append($caches)
            ->getNode();
    }

    public function testAcceptElementsAtParentLevelCannotBeCombinedWithWrappingArrays()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('The node "root" cannot both wrap arrays into "options" with ->acceptAndWrap() and accept the elements of "stores" at its level.');

        (new ArrayNodeDefinition('root'))
            ->beforeNormalization()->ifArray()->then(static fn ($v) => $v)->end()
            ->acceptAndWrap(['array'], 'options')
            ->children()
                ->arrayNode('options')
                    ->children()
                        ->scalarNode('timeout')->end()
                    ->end()
                ->end()
            ->end()
            ->append(self::createElementsAtParentLevelNode())
            ->getNode();
    }

    public function testFindShouldThrowExceptionIfNodeDoesNotExistInRootNode()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Node with name "child" does not exist in the current node "root".');

        $rootNode = new ArrayNodeDefinition('root');
        $rootNode
            ->children()
                ->arrayNode('social_media_channels')->end()
            ->end()
        ;

        $rootNode->find('child');
    }

    public function testFindShouldHandleComplexConfigurationProperly()
    {
        $rootNode = new ArrayNodeDefinition('root');
        $rootNode
            ->children()
                ->arrayNode('social_media_channels')
                    ->children()
                        ->booleanNode('enable')->end()
                        ->arrayNode('twitter')->end()
                        ->arrayNode('facebook')->end()
                        ->arrayNode('instagram')
                            ->children()
                                ->booleanNode('enable')->end()
                                ->arrayNode('accounts')->end()
                            ->end()
                    ->end()
                ->end()
                ->append(
                    $mailerNode = (new ArrayNodeDefinition('mailer'))
                        ->children()
                            ->booleanNode('enable')->end()
                            ->arrayNode('transports')->end()
                        ->end()
                )
            ->end()
        ;

        $this->assertNode('social_media_channels', ArrayNodeDefinition::class, $rootNode->find('social_media_channels'));
        $this->assertNode('enable', BooleanNodeDefinition::class, $rootNode->find('social_media_channels.enable'));
        $this->assertNode('twitter', ArrayNodeDefinition::class, $rootNode->find('social_media_channels.twitter'));
        $this->assertNode('facebook', ArrayNodeDefinition::class, $rootNode->find('social_media_channels.facebook'));
        $this->assertNode('instagram', ArrayNodeDefinition::class, $rootNode->find('social_media_channels.instagram'));
        $this->assertNode('enable', BooleanNodeDefinition::class, $rootNode->find('social_media_channels.instagram.enable'));
        $this->assertNode('accounts', ArrayNodeDefinition::class, $rootNode->find('social_media_channels.instagram.accounts'));

        $this->assertNode('enable', BooleanNodeDefinition::class, $mailerNode->find('enable'));
        $this->assertNode('transports', ArrayNodeDefinition::class, $mailerNode->find('transports'));
    }

    public function testFindShouldWorkProperlyForNonDefaultPathSeparator()
    {
        $rootNode = new ArrayNodeDefinition('root');
        $rootNode
            ->setPathSeparator('.|')
            ->children()
            ->arrayNode('mailer.configuration')
                ->children()
                    ->booleanNode('enable')->end()
                    ->arrayNode('transports')->end()
                ->end()
            ->end()
        ;

        $this->assertNode('mailer.configuration', ArrayNodeDefinition::class, $rootNode->find('mailer.configuration'));
        $this->assertNode('enable', BooleanNodeDefinition::class, $rootNode->find('mailer.configuration.|enable'));
        $this->assertNode('transports', ArrayNodeDefinition::class, $rootNode->find('mailer.configuration.|transports'));
    }

    protected function assertNode(string $expectedName, string $expectedType, NodeDefinition $actualNode): void
    {
        $this->assertInstanceOf($expectedType, $actualNode);
        $this->assertSame($expectedName, $this->getField($actualNode, 'name'));
    }

    protected function getField(object $object, string $field)
    {
        $reflection = new \ReflectionProperty($object, $field);

        return $reflection->getValue($object);
    }

    private static function createElementsAtParentLevelNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('stores');
        $node
            ->useAttributeAsKey('name')
            ->acceptElementsAtParentLevel()
            ->prototype('array')
                ->children()
                    ->scalarNode('host')->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
