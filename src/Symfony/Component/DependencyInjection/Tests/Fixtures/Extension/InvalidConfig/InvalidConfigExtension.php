<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Extension\InvalidConfig;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;

class InvalidConfigExtension extends BaseExtension
{
    public function load(array $configs, ContainerBuilder $container)
    {
    }
}
