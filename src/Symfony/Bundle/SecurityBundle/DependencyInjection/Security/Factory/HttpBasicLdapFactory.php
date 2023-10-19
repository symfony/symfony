<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Exception\LogicException;

/**
 * HttpBasicFactory creates services for HTTP basic authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @internal
 */
class HttpBasicLdapFactory extends HttpBasicFactory
{
    use LdapFactoryTrait;

    public function create(ContainerBuilder $container, string $id, array $config, string $userProvider, ?string $defaultEntryPoint): array
    {
        $provider = 'security.authentication.provider.ldap_bind.'.$id;
        $definition = $container
            ->setDefinition($provider, new ChildDefinition('security.authentication.provider.ldap_bind'))
            ->replaceArgument(0, new Reference($userProvider))
            ->replaceArgument(1, new Reference('security.user_checker.'.$id))
            ->replaceArgument(2, $id)
            ->replaceArgument(3, new Reference($config['service']))
            ->replaceArgument(4, $config['dn_string'])
            ->replaceArgument(6, $config['search_dn'])
            ->replaceArgument(7, $config['search_password'])
        ;

        // entry point
        $entryPointId = $defaultEntryPoint;

        if (null === $entryPointId) {
            $entryPointId = 'security.authentication.basic_entry_point.'.$id;
            $container
                ->setDefinition($entryPointId, new ChildDefinition('security.authentication.basic_entry_point'))
                ->addArgument($config['realm']);
        }

        if (!empty($config['query_string'])) {
            if ('' === $config['search_dn'] || '' === $config['search_password']) {
                throw new LogicException('Using the "query_string" config without using a "search_dn" and a "search_password" is not supported.');
            }
            $definition->addMethodCall('setQueryString', [$config['query_string']]);
        }

        // listener
        $listenerId = 'security.authentication.listener.basic.'.$id;
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.basic'));
        $listener->replaceArgument(2, $id);
        $listener->replaceArgument(3, new Reference($entryPointId));

        return [$provider, $listenerId, $entryPointId];
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        parent::addConfiguration($node);

        $node
            ->children()
                ->scalarNode('service')->defaultValue('ldap')->end()
                ->scalarNode('dn_string')->defaultValue('{username}')->end()
                ->scalarNode('query_string')->end()
                ->scalarNode('search_dn')->defaultValue('')->end()
                ->scalarNode('search_password')->defaultValue('')->end()
            ->end()
        ;
    }
}
