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

use Symfony\Component\Config\Definition\Builder\NodeBuilder as BaseNodeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition as BaseVariableNodeDefinition;

class NodeBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionWhenTryingToCreateANonRegisteredNodeType()
    {
        $builder = new BaseNodeBuilder();
        $builder->node('', 'foobar');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionWhenTheNodeClassIsNotFound()
    {
        $builder = new BaseNodeBuilder();
        $builder
            ->setNodeClass('noclasstype', '\\foo\\bar\\noclass')
            ->node('', 'noclasstype');
    }

    public function testAddingANewNodeType()
    {
        $class = __NAMESPACE__.'\\SomeNodeDefinition';

        $builder = new BaseNodeBuilder();
        $node = $builder
            ->setNodeClass('newtype', $class)
            ->node('', 'newtype');

        $this->assertEquals(get_class($node), $class);
    }

    public function testOverridingAnExistingNodeType()
    {
        $class = __NAMESPACE__.'\\SomeNodeDefinition';

        $builder = new BaseNodeBuilder();
        $node = $builder
            ->setNodeClass('variable', $class)
            ->node('', 'variable');

        $this->assertEquals(get_class($node), $class);
    }

    public function testNodeTypesAreNotCaseSensitive()
    {
        $builder = new BaseNodeBuilder();

        $node1 = $builder->node('', 'VaRiAbLe');
        $node2 = $builder->node('', 'variable');

        $this->assertEquals(get_class($node1), get_class($node2));

        $builder->setNodeClass('CuStOm', __NAMESPACE__.'\\SomeNodeDefinition');

        $node1 = $builder->node('', 'CUSTOM');
        $node2 = $builder->node('', 'custom');

        $this->assertEquals(get_class($node1), get_class($node2));
    }
}

class SomeNodeDefinition extends BaseVariableNodeDefinition
{
}
