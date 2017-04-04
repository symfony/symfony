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
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidDefinitionException;

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
        $this->assertTrue(in_array($child, $this->getField($parent, 'children')));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidDefinitionException
     * @dataProvider providePrototypeNodeSpecificCalls
     */
    public function testPrototypeNodeSpecificOption($method, $args)
    {
        $node = new ArrayNodeDefinition('root');

        call_user_func_array(array($node, $method), $args);

        $node->getNode();
    }

    public function providePrototypeNodeSpecificCalls()
    {
        return array(
            array('defaultValue', array(array())),
            array('addDefaultChildrenIfNoneSet', array()),
            array('requiresAtLeastOneElement', array()),
            array('useAttributeAsKey', array('foo')),
        );
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidDefinitionException
     */
    public function testConcreteNodeSpecificOption()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->addDefaultsIfNotSet()
            ->prototype('array')
        ;
        $node->getNode();
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidDefinitionException
     */
    public function testPrototypeNodesCantHaveADefaultValueWhenUsingDefaultChildren()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->defaultValue(array())
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
        $this->assertEquals(array(array()), $tree->getDefaultValue());
    }

    /**
     * @dataProvider providePrototypedArrayNodeDefaults
     */
    public function testPrototypedArrayNodeDefault($args, $shouldThrowWhenUsingAttrAsKey, $shouldThrowWhenNotUsingAttrAsKey, $defaults)
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

    public function providePrototypedArrayNodeDefaults()
    {
        return array(
            array(null, true, false, array(array())),
            array(2, true, false, array(array(), array())),
            array('2', false, true, array('2' => array())),
            array('foo', false, true, array('foo' => array())),
            array(array('foo'), false, true, array('foo' => array())),
            array(array('foo', 'bar'), false, true, array('foo' => array(), 'bar' => array())),
        );
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

        $this->assertInstanceOf('Symfony\Component\Config\Definition\PrototypedArrayNode', $node);
        $this->assertInstanceOf('Symfony\Component\Config\Definition\PrototypedArrayNode', $node->getPrototype());
    }

    public function testEnabledNodeDefaults()
    {
        $node = new ArrayNodeDefinition('root');
        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('foo')->defaultValue('bar')->end()
        ;

        $this->assertEquals(array('enabled' => false, 'foo' => 'bar'), $node->getNode()->getDefaultValue());
    }

    /**
     * @dataProvider getEnableableNodeFixtures
     */
    public function testTrueEnableEnabledNode($expected, $config, $message)
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
        $this->assertEquals(array('enabled' => false), $this->getField($node, 'falseEquivalent'));
        $this->assertEquals(array('enabled' => true), $this->getField($node, 'trueEquivalent'));
        $this->assertEquals(array('enabled' => true), $this->getField($node, 'nullEquivalent'));

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

    public function getEnableableNodeFixtures()
    {
        return array(
            array(array('enabled' => true, 'foo' => 'bar'), array(true), 'true enables an enableable node'),
            array(array('enabled' => true, 'foo' => 'bar'), array(null), 'null enables an enableable node'),
            array(array('enabled' => true, 'foo' => 'bar'), array(array('enabled' => true)), 'An enableable node can be enabled'),
            array(array('enabled' => true, 'foo' => 'baz'), array(array('foo' => 'baz')), 'any configuration enables an enableable node'),
            array(array('enabled' => false, 'foo' => 'baz'), array(array('foo' => 'baz', 'enabled' => false)), 'An enableable node can be disabled'),
            array(array('enabled' => false, 'foo' => 'bar'), array(false), 'false disables an enableable node'),
        );
    }

    protected function getField($object, $field)
    {
        $reflection = new \ReflectionProperty($object, $field);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
