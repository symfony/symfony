<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyAccess\DependencyInjection\RemovePropertyAccessCachePass;

/**
 * Provides the property accessor services.
 */
#[RequiredBundle(ServicesBundle::class)]
class PropertyAccessBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemovePropertyAccessCachePass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->info('Property access configuration')
            ->canBeDisabled()
            ->children()
                ->booleanNode('magic_call')->defaultFalse()->end()
                ->booleanNode('magic_get')->defaultTrue()->end()
                ->booleanNode('magic_set')->defaultTrue()->end()
                ->booleanNode('throw_exception_on_invalid_index')->defaultFalse()->end()
                ->booleanNode('throw_exception_on_invalid_property_path')->defaultTrue()->end()
                ->booleanNode('wildcard_reads')
                    ->info('Enables reading every element of a collection through a "[*]" wildcard.')
                    ->defaultFalse()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/property_access.php');

        $magicMethods = PropertyAccessor::DISALLOW_MAGIC_METHODS;
        $magicMethods |= $config['magic_call'] ? PropertyAccessor::MAGIC_CALL : 0;
        $magicMethods |= $config['magic_get'] ? PropertyAccessor::MAGIC_GET : 0;
        $magicMethods |= $config['magic_set'] ? PropertyAccessor::MAGIC_SET : 0;

        $throw = PropertyAccessor::DO_NOT_THROW;
        $throw |= $config['throw_exception_on_invalid_index'] ? PropertyAccessor::THROW_ON_INVALID_INDEX : 0;
        $throw |= $config['throw_exception_on_invalid_property_path'] ? PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH : 0;

        $container
            ->getDefinition('property_accessor')
            ->replaceArgument(0, $magicMethods)
            ->replaceArgument(1, $throw)
            ->replaceArgument(5, $config['wildcard_reads'])
        ;

        if (!class_exists(ArrayAdapter::class)) {
            return;
        }

        $cache = $container->register('cache.property_access', AdapterInterface::class);

        if ($container->getParameter('kernel.debug')) {
            $cache->setClass(ArrayAdapter::class);
            $cache->setArguments([0, false]);
        } else {
            $cache->setFactory([PropertyAccessor::class, 'createCache']);
            $cache->setArguments(['', 0, new Parameter('container.build_id'), new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]);
            $cache->addTag('cache.pool', ['clearer' => 'cache.system_clearer']);
            $cache->addTag('monolog.logger', ['channel' => 'cache']);
        }
    }
}
