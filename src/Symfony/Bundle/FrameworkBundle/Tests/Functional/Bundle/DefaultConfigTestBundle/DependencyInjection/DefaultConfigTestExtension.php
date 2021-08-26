<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DefaultConfigTestBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class DefaultConfigTestExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('default_config_test', $config['foo']);
        $container->setParameter('default_config_test', $config['baz']);
    }
}
