<?php

namespace Symfony\Tests\Component\Config\Definition;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class NodeBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testNodeBuilder()
    {
        $nodeBuilder = new NodeBuilder('root', array());
        $childNode = new NodeBuilder('child', array());

        $ret = $nodeBuilder->nodeBuilder($childNode);
        $this->assertEquals(array('child' => $childNode), $nodeBuilder->children);
        $this->assertEquals($nodeBuilder, $ret);
    }
}