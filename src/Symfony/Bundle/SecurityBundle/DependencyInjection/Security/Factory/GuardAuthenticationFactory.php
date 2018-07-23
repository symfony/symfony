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
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Configures the "guard" authentication provider key under a firewall.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class GuardAuthenticationFactory implements SecurityFactoryInterface
{
    public function getPosition()
    {
        return 'pre_auth';
    }

    public function getKey()
    {
        return 'guard';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->fixXmlConfig('authenticator')
            ->children()
                ->scalarNode('provider')
                    ->info('A key from the "providers" section of your security config, in case your user provider is different than the firewall')
                ->end()
                ->scalarNode('entry_point')
                    ->info('A service id (of one of your authenticators) whose start() method should be called when an anonymous user hits a page that requires authentication')
                    ->defaultValue(null)
                ->end()
                ->arrayNode('authenticators')
                    ->info('An array of service ids for all of your "authenticators"')
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $authenticatorIds = $config['authenticators'];
        $authenticatorReferences = array();
        foreach ($authenticatorIds as $authenticatorId) {
            $authenticatorReferences[] = new Reference($authenticatorId);
        }

        $authenticators = new IteratorArgument($authenticatorReferences);

        // configure the GuardAuthenticationFactory to have the dynamic constructor arguments
        $providerId = 'security.authentication.provider.guard.'.$id;
        $container
            ->setDefinition($providerId, new ChildDefinition('security.authentication.provider.guard'))
            ->replaceArgument(0, $authenticators)
            ->replaceArgument(1, new Reference($userProvider))
            ->replaceArgument(2, $id)
            ->replaceArgument(3, new Reference('security.user_checker.'.$id))
        ;

        // listener
        $listenerId = 'security.authentication.listener.guard.'.$id;
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.guard'));
        $listener->replaceArgument(2, $id);
        $listener->replaceArgument(3, $authenticators);

        // determine the entryPointId to use
        $entryPointId = $this->determineEntryPoint($defaultEntryPoint, $config);

        // this is always injected - then the listener decides if it should be used
        $container
            ->getDefinition($listenerId)
            ->addTag('security.remember_me_aware', array('id' => $id, 'provider' => $userProvider));

        return array($providerId, $listenerId, $entryPointId);
    }

    private function determineEntryPoint($defaultEntryPointId, array $config)
    {
        if ($defaultEntryPointId) {
            // explode if they've configured the entry_point, but there is already one
            if ($config['entry_point']) {
                throw new \LogicException(sprintf(
                    'The guard authentication provider cannot use the "%s" entry_point because another entry point is already configured by another provider! Either remove the other provider or move the entry_point configuration as a root key under your firewall (i.e. at the same level as "guard").',
                    $config['entry_point']
                ));
            }

            return $defaultEntryPointId;
        }

        if ($config['entry_point']) {
            // if it's configured explicitly, use it!
            return $config['entry_point'];
        }

        $authenticatorIds = $config['authenticators'];
        if (1 == count($authenticatorIds)) {
            // if there is only one authenticator, use that as the entry point
            return array_shift($authenticatorIds);
        }

        // we have multiple entry points - we must ask them to configure one
        throw new \LogicException(sprintf(
            'Because you have multiple guard configurators, you need to set the "guard.entry_point" key to one of your configurators (%s)',
            implode(', ', $authenticatorIds)
        ));
    }
}
