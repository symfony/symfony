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

    /**
     * @dataProvider providePrototypeNodeSpecificCalls
     */
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

    /**
     * @dataProvider providePrototypedArrayNodeDefaults
     */
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

    /**
     * @dataProvider getEnableableNodeFixtures
     */
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

    public function testUnsetChild()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->children()
                ->scalarNode('value')
                    ->beforeNormalization()
                        ->ifTrue(fn ($value) => empty($value))
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
}
