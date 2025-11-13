<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\AccessToken\Cas\Cas2Handler;

class CasTokenHandlerFactory implements TokenHandlerFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array|string $config): void
    {
        $container->setDefinition($id, new ChildDefinition('security.access_token_handler.cas'));

        $container
            ->register('security.access_token_handler.cas', Cas2Handler::class)
            ->setArguments([
                new Reference('request_stack'),
                $config['validation_url'],
                $config['prefix'],
                $config['http_client'] ? new Reference($config['http_client']) : null,
            ]);
    }

    public function getKey(): string
    {
        return 'cas';
    }

    public function addConfiguration(NodeBuilder $node): void
    {
        $node
            ->arrayNode($this->getKey())
                ->children()
                    ->scalarNode('validation_url')
                        ->info('CAS server validation URL')
                        ->isRequired()
                    ->end()
                    ->scalarNode('prefix')
                        ->info('CAS prefix')
                        ->defaultValue('cas')
                    ->end()
                    ->scalarNode('http_client')
                        ->info('HTTP Client service')
                        ->defaultNull()
                    ->end()
                ->end()
            ->end();
    }
}
