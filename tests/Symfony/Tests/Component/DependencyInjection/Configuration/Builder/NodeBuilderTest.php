<?php

namespace Symfony\Tests\Component\DependencyInjection\Configuration;

use Symfony\Component\DependencyInjection\Configuration\Builder\NodeBuilder;

class NodeBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testAddNodeBuilder()
    {
        $nodeBuilder = new NodeBuilder('root', array());
        $childNode = new NodeBuilder('child', array());

        $ret = $nodeBuilder->addNodeBuilder($childNode);
        $this->assertEquals(array('child' => $childNode), $nodeBuilder->children);
        $this->assertEquals($nodeBuilder, $ret);
    }
}