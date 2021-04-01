<?php


namespace Symfony\Component\Config\Tests\Fixtures\Configuration;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;

class CustomNodeDefinition extends NodeDefinition
{
    protected function createNode()
    {
        return new CustomNode();
    }
}
