<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Semaphore\Store\StoreFactory;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Provides the semaphore services.
 */
#[RequiredBundle(ServicesBundle::class)]
class SemaphoreBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->acceptAndWrap(['string'], 'resources')
            ->canBeDisabled()
            ->beforeNormalization()
                ->ifArray()
                ->then(static function ($v) {
                    if (!isset($v['resources']) && !isset($v['resource'])) {
                        $v = ['resources' => $v];
                        if (\array_key_exists('enabled', $v['resources'])) {
                            $v['enabled'] = $v['resources']['enabled'];
                            unset($v['resources']['enabled']);
                        }
                    }

                    return $v;
                })
            ->end()
            ->children()
                ->arrayNode('resources', 'resource')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->acceptAndWrap(['string'], 'default')
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($v) {
                            if (!array_is_list($v)) {
                                return $v;
                            }

                            $resources = [];
                            foreach ($v as $resource) {
                                $resources[] = \is_array($resource) && isset($resource['name'])
                                    ? [$resource['name'] => $resource['value']]
                                    : ['default' => $resource]
                                ;
                            }

                            return array_merge_recursive([], ...$resources);
                        })
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/semaphore.php');

        if (!interface_exists(DenormalizerInterface::class)) {
            $container->removeDefinition('serializer.normalizer.semaphore_key');
        }

        foreach ($config['resources'] as $resourceName => $resourceStore) {
            $storeDsn = $container->resolveEnvPlaceholders($resourceStore, null, $usedEnvs);

            $storeDefinition = new Definition(PersistingStoreInterface::class);
            $storeDefinition->setFactory([StoreFactory::class, 'createStore']);
            $storeDefinition->setArguments([match (true) {
                $usedEnvs => $resourceStore,
                str_starts_with($storeDsn, 'lock://') => new Reference('lock.'.(substr($storeDsn, 7) ?: 'default').'.factory'),
                !str_contains($resourceStore, '://') => new Reference($resourceStore),
                default => $resourceStore,
            }]);

            $container->setDefinition($storeDefinitionId = '.semaphore.'.$resourceName.'.store.'.$container->hash($storeDsn), $storeDefinition);

            // Generate factories for each resource
            $factoryDefinition = new ChildDefinition('semaphore.factory.abstract');
            $factoryDefinition->replaceArgument(0, new Reference($storeDefinitionId));
            $container->setDefinition('semaphore.'.$resourceName.'.factory', $factoryDefinition);

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->setAlias('semaphore.factory', new Alias('semaphore.'.$resourceName.'.factory', false));
                $container->setAlias(SemaphoreFactory::class, new Alias('semaphore.factory', false));
            } else {
                $container->registerAliasForArgument('semaphore.'.$resourceName.'.factory', SemaphoreFactory::class, $resourceName.'.semaphore.factory', $resourceName);
            }
        }
    }
}
