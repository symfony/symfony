<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Extension\SemiValidConfig;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function __construct($required)
    {
    }

    public function getConfigTreeBuilder()
    {
        return new TreeBuilder('root');
    }
}
