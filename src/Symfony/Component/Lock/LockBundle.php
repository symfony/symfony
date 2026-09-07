<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

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
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Provides the lock services.
 */
#[RequiredBundle(ServicesBundle::class)]
class LockBundle extends AbstractBundle
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
                    if ($v && !isset($v['resources']) && !isset($v['resource'])) {
                        $v = ['resources' => $v];
                        if (\array_key_exists('enabled', $v['resources'])) {
                            $v['enabled'] = $v['resources']['enabled'];
                            unset($v['resources']['enabled']);
                        }
                    }

                    return $v;
                })
            ->end()
            ->validate()
                ->ifTrue(static fn ($v) => $v['enabled'] && !$v['resources'])
                ->thenInvalid('At least one resource must be defined.')
            ->end()
            ->children()
                ->arrayNode('resources', 'resource')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->defaultValue(['default' => [SemaphoreStore::isSupported() ? 'semaphore' : 'flock']])
                    ->acceptAndWrap(['string'], 'default')
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($v) {
                            if (!array_is_list($v)) {
                                return isset($v['service_id']) ? ['default' => $v] : $v;
                            }

                            $resources = [];
                            foreach ($v as $resource) {
                                [$name, $store] = \is_array($resource) && isset($resource['name'])
                                    ? [$resource['name'], $resource['value']]
                                    : ['default', $resource]
                                ;
                                $resources[] = [$name => \is_array($store) && !array_is_list($store) ? [$store] : $store];
                            }

                            return array_merge_recursive([], ...$resources);
                        })
                    ->end()
                    ->prototype('array')
                        ->info('Each store is a DSN, a store keyword, the id of a service holding a connection, or an array with a "service_id" key and an "advisory" key.')
                        ->performNoDeepMerging()
                        ->acceptAndWrap(['string'])
                        // acceptAndWrap() doesn't list null as an accepted value on purpose,
                        // yet the XML loader can yield some and we should convert them to 'null'
                        ->beforeNormalization()->ifNull()->then(static fn () => ['null'])->end()
                        ->beforeNormalization()
                            ->ifTrue(static fn ($v) => \is_array($v) && (isset($v['service_id']) || isset($v['advisory'])))
                            ->then(static fn ($v) => [$v])
                        ->end()
                        ->variablePrototype()
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(static fn ($v) => ['service_id' => $v['service_id'] ?? null, 'advisory' => $v['advisory'] ?? false] + $v)
                            ->end()
                            ->validate()
                                ->ifTrue(static fn ($v) => \is_array($v) && (2 !== \count($v) || !\is_string($v['service_id']) || !\is_bool($v['advisory'])))
                                ->thenInvalid('A lock store must be a string or an array with a "service_id" string and an optional "advisory" boolean, got %s.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/lock.php');

        if (!interface_exists(DenormalizerInterface::class)) {
            $container->removeDefinition('serializer.normalizer.lock_key');
        }

        foreach ($config['resources'] as $resourceName => $resourceStores) {
            if (!$resourceStores) {
                continue;
            }

            // Generate stores
            $storeDefinitions = [];
            foreach ($resourceStores as $resourceStore) {
                if (\in_array($resourceStore, ['flock', 'semaphore'], true)) {
                    $storeDefinitionId = \sprintf('.lock.%s.store', $resourceStore);
                    $storeDefinitions[] = new Reference($storeDefinitionId);
                    $container->getDefinition($storeDefinitionId)->addTag('lock.store');
                    continue;
                }
                $usedEnvs = [];
                $storeDsn = $container->resolveEnvPlaceholders($resourceStore, null, $usedEnvs);
                $advisory = false;
                if (\is_array($resourceStore)) {
                    $advisory = $resourceStore['advisory'];
                    $resourceStore = new Reference($resourceStore['service_id']);
                } elseif (!$usedEnvs && !str_contains($resourceStore, ':') && !\in_array($resourceStore, ['flock', 'semaphore', 'in-memory', 'null'], true)) {
                    $resourceStore = new Reference($resourceStore);
                }
                $storeDefinition = new Definition(PersistingStoreInterface::class);
                $storeDefinition
                    ->setFactory([StoreFactory::class, 'createStore'])
                    ->setArguments($advisory ? [$resourceStore, true] : [$resourceStore])
                    ->addTag('lock.store');

                $container->setDefinition($storeDefinitionId = '.lock.'.$resourceName.'.store.'.$container->hash($storeDsn), $storeDefinition);

                $storeDefinitions[] = new Reference($storeDefinitionId);
            }

            // Wrap array of stores with CombinedStore
            if (\count($storeDefinitions) > 1) {
                $combinedDefinition = new ChildDefinition('lock.store.combined.abstract');
                $combinedDefinition->replaceArgument(0, $storeDefinitions);
                $container->setDefinition($storeDefinitionId = '.lock.'.$resourceName.'.store.'.$container->hash($resourceStores), $combinedDefinition);
            }

            // Generate factories for each resource
            $factoryDefinition = new ChildDefinition('lock.factory.abstract');
            $factoryDefinition->replaceArgument(0, new Reference($storeDefinitionId));
            $container->setDefinition('lock.'.$resourceName.'.factory', $factoryDefinition);

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->setAlias('lock.factory', new Alias('lock.'.$resourceName.'.factory', false));
                $container->setAlias(LockFactory::class, new Alias('lock.factory', false));
            } else {
                $container->registerAliasForArgument('lock.'.$resourceName.'.factory', LockFactory::class, $resourceName.'.lock.factory', $resourceName);
            }
        }
    }
}
