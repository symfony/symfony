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
use Symfony\Component\Security\Http\EntryPoint\FallbackAuthenticationEntryPointInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class RegisterEntryPointPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
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
            $fallbackEntryPoints = [];
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

                if (is_a($authenticatorClass, FallbackAuthenticationEntryPointInterface::class, true)) {
                    $fallbackEntryPoints[$key] = $authenticatorId;
                } elseif (is_a($authenticatorClass, AuthenticationEntryPointInterface::class, true)) {
                    $entryPoints[$key] = $authenticatorId;
                }
            }

            // an explicit option only, never inferred: re-authentication is not something
            // to start by accident on a firewall that happens to have a single entry point
            if ($container->hasDefinition($exceptionListenerId = 'security.exception_listener.'.$firewallName)
                && \array_key_exists(9, ($exceptionListener = $container->getDefinition($exceptionListenerId))->getArguments())
                && null !== $configuredReAuthEntryPoint = $exceptionListener->getArgument(9)
            ) {
                $exceptionListener->replaceArgument(9, new Reference($entryPoints[$configuredReAuthEntryPoint] ?? $fallbackEntryPoints[$configuredReAuthEntryPoint] ?? $configuredReAuthEntryPoint));
            }

            if (!$entryPoints && !$fallbackEntryPoints) {
                continue;
            }

            $config = $container->getDefinition('security.firewall.map.config.'.$firewallName);
            $configuredEntryPoint = $config->getArgument(7);

            if (null !== $configuredEntryPoint) {
                // allow entry points to be configured by authenticator key (e.g. "http_basic")
                $entryPoint = $entryPoints[$configuredEntryPoint] ?? $fallbackEntryPoints[$configuredEntryPoint] ?? $configuredEntryPoint;
            } else {
                // a fallback entry point only stands in for a firewall that declares no other
                // one, so that an authenticator gaining one never makes a firewall ambiguous
                $entryPoints = $entryPoints ?: $fallbackEntryPoints;

                if (1 === \count($entryPoints)) {
                    $entryPoint = array_shift($entryPoints);
                } else {
                    $entryPointNames = [];
                    foreach ($entryPoints as $key => $serviceId) {
                        $entryPointNames[] = is_numeric($key) ? $serviceId : $key;
                    }

                    throw new InvalidConfigurationException(\sprintf('Because you have multiple authenticators in firewall "%s", you need to set the "entry_point" key to one of your authenticators ("%s") or a service ID implementing "%s". The "entry_point" determines what should happen (e.g. redirect to "/login") when an anonymous user tries to access a protected page.', $firewallName, implode('", "', $entryPointNames), AuthenticationEntryPointInterface::class));
                }
            }

            $config->replaceArgument(7, $entryPoint);
            $container->getDefinition('security.exception_listener.'.$firewallName)->replaceArgument(4, new Reference($entryPoint));
        }
    }
}
