<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class RegisterEntryPointPass implements CompilerPassInterface
{
    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('security.firewalls')) {
            return;
        }

        $firewalls = $container->getParameter('security.firewalls');
        foreach ($firewalls as $firewallName) {
            if (!$container->hasDefinition('security.authenticator.manager.'.$firewallName) || !$container->hasParameter('security.'.$firewallName.'._indexed_authenticators')) {
                continue;
            }

            $entryPoints = [];
            $indexedAuthenticators = $container->getParameter('security.'.$firewallName.'._indexed_authenticators');
            // this is a compile-only parameter, removing it cleans up space and avoids unintended usage
            $container->getParameterBag()->remove('security.'.$firewallName.'._indexed_authenticators');
            foreach ($indexedAuthenticators as $key => $authenticatorId) {
                if (!$container->has($authenticatorId)) {
                    continue;
                }

                // because this pass runs before ResolveChildDefinitionPass, child definitions didn't inherit the parent class yet
                $definition = $container->findDefinition($authenticatorId);
                while (!($authenticatorClass = $definition->getClass()) && $definition instanceof ChildDefinition) {
                    $definition = $container->findDefinition($definition->getParent());
                }

                if (is_a($authenticatorClass, AuthenticationEntryPointInterface::class, true)) {
                    $entryPoints[$key] = $authenticatorId;
                }
            }

            if (!$entryPoints) {
                continue;
            }

            $config = $container->getDefinition('security.firewall.map.config.'.$firewallName);
            $configuredEntryPoint = $config->getArgument(7);

            if (null !== $configuredEntryPoint) {
                // allow entry points to be configured by authenticator key (e.g. "http_basic")
                $entryPoint = $entryPoints[$configuredEntryPoint] ?? $configuredEntryPoint;
            } elseif (1 === \count($entryPoints)) {
                $entryPoint = array_shift($entryPoints);
            } else {
                $entryPointNames = [];
                foreach ($entryPoints as $key => $serviceId) {
                    $entryPointNames[] = is_numeric($key) ? $serviceId : $key;
                }

                throw new InvalidConfigurationException(sprintf('Because you have multiple authenticators in firewall "%s", you need to set the "entry_point" key to one of your authenticators ("%s") or a service ID implementing "%s". The "entry_point" determines what should happen (e.g. redirect to "/login") when an anonymous user tries to access a protected page.', $firewallName, implode('", "', $entryPointNames), AuthenticationEntryPointInterface::class));
            }

            $config->replaceArgument(7, $entryPoint);
            $container->getDefinition('security.exception_listener.'.$firewallName)->replaceArgument(4, new Reference($entryPoint));
        }
    }
}
