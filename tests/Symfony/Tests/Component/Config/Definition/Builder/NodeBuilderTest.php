<?php

namespace Symfony\Tests\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;

class NodeBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionWhenTryingToCreateANonRegisteredNodeType()
    {
        $builder = new NodeBuilder();
        $builder->node('', 'foobar');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionWhenTheNodeClassIsNotFound()
    {
        $builder = new NodeBuilder();
        $builder
            ->setNodeClass('noclasstype', '\\foo\\bar\\noclass')
            ->node('', 'noclasstype');
    }

    public function testAddingANewNodeType()
    {
        $class = __NAMESPACE__.'\\SomeNodeDefinition';
        
        $builder = new NodeBuilder();
        $node = $builder
            ->setNodeClass('newtype', $class)
            ->node('', 'newtype');

        $this->assertEquals(get_class($node), $class);
    }

    public function testOverridingAnExistingNodeType()
    {
        $class = __NAMESPACE__.'\\SomeNodeDefinition';

        $builder = new NodeBuilder();
        $node = $builder
            ->setNodeClass('variable', $class)
            ->node('', 'variable');

        $this->assertEquals(get_class($node), $class);
    }

    public function testNodeTypesAreNotCaseSensitive()
    {
        $builder = new NodeBuilder();

        $node1 = $builder->node('', 'VaRiAbLe');
        $node2 = $builder->node('', 'variable');

        $this->assertEquals(get_class($node1), get_class($node2));

        $builder->setNodeClass('CuStOm', __NAMESPACE__.'\\SomeNodeDefinition');

        $node1 = $builder->node('', 'CUSTOM');
        $node2 = $builder->node('', 'custom');

        $this->assertEquals(get_class($node1), get_class($node2));
    }    
}

class SomeNodeDefinition extends VariableNodeDefinition
{
}