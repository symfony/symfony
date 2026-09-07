<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\UidValueResolver;
use Symfony\Component\Uid\DependencyInjection\RemoveUuid47TransformerPass;

/**
 * Provides the UID factories and the UUIDv47 transformer.
 */
#[RequiredBundle(ServicesBundle::class)]
class UidBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveUuid47TransformerPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->enumNode('default_uuid_version')
                    ->values([7, 6, 4, 1])
                    ->defaultValue(7)
                ->end()
                ->enumNode('name_based_uuid_version')
                    ->defaultValue(5)
                    ->values([5, 3])
                ->end()
                ->scalarNode('name_based_uuid_namespace')
                    ->cannotBeEmpty()
                ->end()
                ->enumNode('time_based_uuid_version')
                    ->values([7, 6, 1])
                    ->defaultValue(7)
                ->end()
                ->scalarNode('time_based_uuid_node')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('uuid47_secret')
                    ->info('A high-entropy secret used by the "uuid47_transformer" service. Defaults to the "kernel.secret" parameter; the service is not registered when neither is defined.')
                    ->defaultNull()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/uid.php');

        $container->getDefinition('uuid.factory')
            ->setArguments([
                $config['default_uuid_version'],
                $config['time_based_uuid_version'],
                $config['name_based_uuid_version'],
                UuidV4::class,
                $config['time_based_uuid_node'] ?? null,
                $config['name_based_uuid_namespace'] ?? null,
            ])
        ;

        if (isset($config['name_based_uuid_namespace'])) {
            $container->getDefinition('name_based_uuid.factory')
                ->setArguments([$config['name_based_uuid_namespace']]);
        }

        if (null !== $config['uuid47_secret']) {
            $container->getDefinition('uuid47_transformer')
                ->setArguments([$config['uuid47_secret']]);
        }

        if (!class_exists(UidValueResolver::class)) {
            $container->removeDefinition('argument_resolver.uid');
        }
    }
}
