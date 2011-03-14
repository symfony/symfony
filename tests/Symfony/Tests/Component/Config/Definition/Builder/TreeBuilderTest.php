<?php

namespace Symfony\Tests\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

require __DIR__.'/../../Fixtures/Builder/NodeBuilder.php';
require __DIR__.'/../../Fixtures/Builder/BarNodeDefinition.php';
require __DIR__.'/../../Fixtures/Builder/VariableNodeDefinition.php';

class TreeBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testUsingACustomNodeBuilder()
    {
        $builder = new TreeBuilder();
        $root = $builder->root('custom', 'array', new NodeBuilder());

        $nodeBuilder = $root->children();

        $this->assertEquals(get_class($nodeBuilder), 'Symfony\Tests\Component\Config\Definition\Builder\NodeBuilder');

        $nodeBuilder = $nodeBuilder->arrayNode('deeper')->children();

        $this->assertEquals(get_class($nodeBuilder), 'Symfony\Tests\Component\Config\Definition\Builder\NodeBuilder');
    }

    public function testOverrideABuiltInNodeType()
    {
        $builder = new TreeBuilder();
        $root = $builder->root('override', 'array', new NodeBuilder());

        $definition = $root->children()->variableNode('variable');

        $this->assertEquals(get_class($definition), 'Symfony\Tests\Component\Config\Definition\Builder\VariableNodeDefinition');
    }

    public function testAddANodeType()
    {
        $builder = new TreeBuilder();
        $root = $builder->root('override', 'array', new NodeBuilder());

        $definition = $root->children()->barNode('variable');

        $this->assertEquals(get_class($definition), 'Symfony\Tests\Component\Config\Definition\Builder\BarNodeDefinition');
    }

    public function testCreateABuiltInNodeTypeWithACustomNodeBuilder()
    {
        $builder = new TreeBuilder();
        $root = $builder->root('builtin', 'array', new NodeBuilder());

        $definition = $root->children()->booleanNode('boolean');

        $this->assertEquals(get_class($definition), 'Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition');
    }

    public function testPrototypedArrayNodeUseTheCustomNodeBuilder()
    {
        $builder = new TreeBuilder();
        $root = $builder->root('override', 'array', new NodeBuilder());

        $root->prototype('bar')->end();
    }

}