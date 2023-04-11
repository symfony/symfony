<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\FirewallMapInterface;

/**
 * This is a lazy-loading firewall map implementation.
 *
 * Listeners will only be initialized if we really need them.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FirewallMap implements FirewallMapInterface
{
    private ContainerInterface $container;
    private iterable $map;

    public function __construct(ContainerInterface $container, iterable $map)
    {
        $this->container = $container;
        $this->map = $map;
    }

    public function getListeners(Request $request): array
    {
        $context = $this->getFirewallContext($request);

        if (null === $context) {
            return [[], null, null];
        }

        return [$context->getListeners(), $context->getExceptionListener(), $context->getLogoutListener()];
    }

    public function getFirewallConfig(Request $request): ?FirewallConfig
    {
        $context = $this->getFirewallContext($request);

        if (null === $context) {
            return null;
        }

        return $context->getConfig();
    }

    private function getFirewallContext(Request $request): ?FirewallContext
    {
        if ($request->attributes->has('_firewall_context')) {
            $storedContextId = $request->attributes->get('_firewall_context');
            foreach ($this->map as $contextId => $requestMatcher) {
                if ($contextId === $storedContextId) {
                    return $this->container->get($contextId);
                }
            }

            $request->attributes->remove('_firewall_context');
        }

        foreach ($this->map as $contextId => $requestMatcher) {
            if (null === $requestMatcher || $requestMatcher->matches($request)) {
                $request->attributes->set('_firewall_context', $contextId);

                /** @var FirewallContext $context */
                $context = $this->container->get($contextId);

                if ($context->getConfig()?->isStateless() && !$request->attributes->has('_stateless')) {
                    $request->attributes->set('_stateless', true);
                }

                return $context;
            }
        }

        return null;
    }
}
