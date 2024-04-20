<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Extension\ValidConfig;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;

class ValidConfigExtension extends BaseExtension
{
    public function __construct($optional = null)
    {
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
    }
}
