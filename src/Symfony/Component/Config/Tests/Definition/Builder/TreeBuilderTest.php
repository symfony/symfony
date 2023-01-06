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
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Tests\Fixtures\BarNode;
use Symfony\Component\Config\Tests\Fixtures\Builder\BarNodeDefinition;
use Symfony\Component\Config\Tests\Fixtures\Builder\NodeBuilder as CustomNodeBuilder;
use Symfony\Component\Config\Tests\Fixtures\Builder\VariableNodeDefinition;

class TreeBuilderTest extends TestCase
{
    public function testUsingACustomNodeBuilder()
    {
        $builder = new TreeBuilder('custom', 'array', new CustomNodeBuilder());

        $nodeBuilder = $builder->getRootNode()->children();

        $this->assertInstanceOf(CustomNodeBuilder::class, $nodeBuilder);

        $nodeBuilder = $nodeBuilder->arrayNode('deeper')->children();

        $this->assertInstanceOf(CustomNodeBuilder::class, $nodeBuilder);
    }

    public function testOverrideABuiltInNodeType()
    {
        $builder = new TreeBuilder('override', 'array', new CustomNodeBuilder());

        $definition = $builder->getRootNode()->children()->variableNode('variable');

        $this->assertInstanceOf(VariableNodeDefinition::class, $definition);
    }

    public function testAddANodeType()
    {
        $builder = new TreeBuilder('override', 'array', new CustomNodeBuilder());

        $definition = $builder->getRootNode()->children()->barNode('variable');

        $this->assertInstanceOf(BarNodeDefinition::class, $definition);
    }

    public function testCreateABuiltInNodeTypeWithACustomNodeBuilder()
    {
        $builder = new TreeBuilder('builtin', 'array', new CustomNodeBuilder());

        $definition = $builder->getRootNode()->children()->booleanNode('boolean');

        $this->assertInstanceOf(BooleanNodeDefinition::class, $definition);
    }

    public function testPrototypedArrayNodeUseTheCustomNodeBuilder()
    {
        $builder = new TreeBuilder('override', 'array', new CustomNodeBuilder());

        $root = $builder->getRootNode();
        $root->prototype('bar')->end();

        $this->assertInstanceOf(BarNode::class, $root->getNode(true)->getPrototype());
    }

    public function testAnExtendedNodeBuilderGetsPropagatedToTheChildren()
    {
        $builder = new TreeBuilder('propagation');

        $builder->getRootNode()
            ->children()
                ->setNodeClass('extended', 'Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition')
                ->node('foo', 'extended')->end()
                ->arrayNode('child')
                    ->children()
                        ->node('foo', 'extended')
                    ->end()
                ->end()
            ->end()
        ->end();

        $node = $builder->buildTree();
        $children = $node->getChildren();

        $this->assertInstanceOf(BooleanNode::class, $children['foo']);

        $childChildren = $children['child']->getChildren();

        $this->assertInstanceOf(BooleanNode::class, $childChildren['foo']);
    }

    public function testDefinitionInfoGetsTransferredToNode()
    {
        $builder = new TreeBuilder('test');

        $builder->getRootNode()->info('root info')
            ->children()
                ->node('child', 'variable')->info('child info')->defaultValue('default')
            ->end()
        ->end();

        $tree = $builder->buildTree();
        $children = $tree->getChildren();

        $this->assertEquals('root info', $tree->getInfo());
        $this->assertEquals('child info', $children['child']->getInfo());
    }

    public function testDefinitionExampleGetsTransferredToNode()
    {
        $builder = new TreeBuilder('test');

        $builder->getRootNode()
            ->example(['key' => 'value'])
            ->children()
                ->node('child', 'variable')->info('child info')->defaultValue('default')->example('example')
            ->end()
        ->end();

        $tree = $builder->buildTree();
        $children = $tree->getChildren();

        $this->assertIsArray($tree->getExample());
        $this->assertEquals('example', $children['child']->getExample());
    }

    public function testDefaultPathSeparatorIsDot()
    {
        $builder = new TreeBuilder('propagation');

        $builder->getRootNode()
            ->children()
                ->node('foo', 'variable')->end()
                ->arrayNode('child')
                    ->children()
                        ->node('foo', 'variable')
                    ->end()
                ->end()
            ->end()
        ->end();

        $node = $builder->buildTree();
        $children = $node->getChildren();

        $this->assertArrayHasKey('foo', $children);
        $this->assertInstanceOf(BaseNode::class, $children['foo']);
        $this->assertSame('propagation.foo', $children['foo']->getPath());

        $this->assertArrayHasKey('child', $children);
        $childChildren = $children['child']->getChildren();

        $this->assertArrayHasKey('foo', $childChildren);
        $this->assertInstanceOf(BaseNode::class, $childChildren['foo']);
        $this->assertSame('propagation.child.foo', $childChildren['foo']->getPath());
    }

    public function testPathSeparatorIsPropagatedToChildren()
    {
        $builder = new TreeBuilder('propagation');

        $builder->getRootNode()
            ->children()
                ->node('foo', 'variable')->end()
                ->arrayNode('child')
                    ->children()
                        ->node('foo', 'variable')
                    ->end()
                ->end()
            ->end()
        ->end();

        $builder->setPathSeparator('/');
        $node = $builder->buildTree();
        $children = $node->getChildren();

        $this->assertArrayHasKey('foo', $children);
        $this->assertInstanceOf(BaseNode::class, $children['foo']);
        $this->assertSame('propagation/foo', $children['foo']->getPath());

        $this->assertArrayHasKey('child', $children);
        $childChildren = $children['child']->getChildren();

        $this->assertArrayHasKey('foo', $childChildren);
        $this->assertInstanceOf(BaseNode::class, $childChildren['foo']);
        $this->assertSame('propagation/child/foo', $childChildren['foo']->getPath());
    }
}
