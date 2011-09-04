<?php

namespace Symfony\Tests\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\Builder\NodeBuilder as BaseNodeBuilder;

class NodeBuilder extends BaseNodeBuilder
{
    public function barNode($name)
    {
        return $this->node($name, 'bar');
    }

    protected function getNodeClass($type)
    {
        switch ($type) {
            case 'variable':
                return __NAMESPACE__.'\\'.ucfirst($type).'NodeDefinition';
            case 'bar':
                return __NAMESPACE__.'\\'.ucfirst($type).'NodeDefinition';
            default:
                return parent::getNodeClass($type);
        }
    }
}
