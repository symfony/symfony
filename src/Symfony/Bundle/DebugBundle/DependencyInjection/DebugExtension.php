<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\DependencyInjection;

use Symfony\Bundle\DebugBundle\Command\ServerDumpPlaceholderCommand;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\VarDumper\Dumper\ServerDumper;

/**
 * DebugExtension.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->getDefinition('var_dumper.cloner')
            ->addMethodCall('setMaxItems', array($config['max_items']))
            ->addMethodCall('setMinDepth', array($config['min_depth']))
            ->addMethodCall('setMaxString', array($config['max_string_length']));

        if (null === $config['dump_destination']) {
            //no-op
        } elseif (0 === strpos($config['dump_destination'], 'tcp://')) {
            $serverDumperHost = $config['dump_destination'];
            $container->getDefinition('debug.dump_listener')
                ->replaceArgument(1, new Reference('var_dumper.server_dumper'))
            ;
            $container->getDefinition('data_collector.dump')
                ->replaceArgument(4, new Reference('var_dumper.server_dumper'))
            ;
            $container->getDefinition('var_dumper.dump_server')
                ->replaceArgument(0, $serverDumperHost)
            ;
            $container->getDefinition('var_dumper.server_dumper')
                ->replaceArgument(0, $serverDumperHost)
            ;
        } else {
            $container->getDefinition('var_dumper.cli_dumper')
                ->replaceArgument(0, $config['dump_destination'])
            ;
            $container->getDefinition('data_collector.dump')
                ->replaceArgument(4, new Reference('var_dumper.cli_dumper'))
            ;
        }

        if (!isset($serverDumperHost)) {
            $container->getDefinition('var_dumper.command.server_dump')->setClass(ServerDumpPlaceholderCommand::class);
            if (!class_exists(ServerDumper::class)) {
                $container->removeDefinition('var_dumper.command.server_dump');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/debug';
    }
}
