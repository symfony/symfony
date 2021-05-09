<?php


namespace Symfony\Component\Config\Tests\Fixtures\Configuration;

use Symfony\Component\Config\Definition\Builder\AbstractNodeDefinition;

class CustomNodeDefinition extends AbstractNodeDefinition
{
    protected function createNode()
    {
        return new CustomNode();
    }
}
