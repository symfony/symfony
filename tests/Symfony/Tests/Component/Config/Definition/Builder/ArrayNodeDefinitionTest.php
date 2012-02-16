<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

class ArrayNodeDefinitionTest extends \PHPUnit_Framework_TestCase
{
    public function testAppendingSomeNode()
    {
        $parent = new ArrayNodeDefinition('root');
        $child = new ScalarNodeDefinition('child');

        $node = $parent
            ->children()
                ->scalarNode('foo')->end()
                ->scalarNode('bar')->end()
            ->end()
            ->append($child);

        $this->assertCount(3, $this->getField($parent, 'children'));
        $this->assertTrue(in_array($child, $this->getField($parent, 'children')));
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidDefinitionException
     * @dataProvider providePrototypeNodeSpecificCalls
     */
    public function testPrototypeNodeSpecificOption($method, $args)
    {
        $node = new ArrayNodeDefinition('root');

        call_user_method_array($method, $node, $args);

        $node->getNode();
    }

    public function providePrototypeNodeSpecificCalls()
    {
        return array(
            array('defaultValue', array(array())),
            array('requiresAtLeastOneElement', array()),
            array('useAttributeAsKey', array('foo'))
        );
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidDefinitionException
     */
    public function testConcreteNodeSpecificOption()
    {
        $node = new ArrayNodeDefinition('root');
        $node->addDefaultsIfNotSet()->prototype('array');
        $node->getNode();
    }

    protected function getField($object, $field)
    {
        $reflection = new \ReflectionProperty($object, $field);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
