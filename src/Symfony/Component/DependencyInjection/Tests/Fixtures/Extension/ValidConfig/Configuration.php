<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Extension\ValidConfig;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        return new TreeBuilder('root');
    }
}
