<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Extension\SemiValidConfig;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;

class SemiValidConfigExtension extends BaseExtension
{
    public function load(array $configs, ContainerBuilder $container)
    {
    }
}
