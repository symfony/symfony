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
    private const ATTRIBUTE_FIREWALL = '_firewall';
    private const ATTRIBUTE_FIREWALL_CONTEXT = '_firewall_context';
    private const FIREWALL_CONTEXT_PREFIX = 'security.firewall.map.context.';

    public function __construct(
        private ContainerInterface $container,
        private iterable $map,
    ) {
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
        return $this->getFirewallContext($request)?->getConfig();
    }

    private function getFirewallContext(Request $request): ?FirewallContext
    {
        if (!$request->attributes->has(self::ATTRIBUTE_FIREWALL_CONTEXT) && $request->attributes->has(self::ATTRIBUTE_FIREWALL)) {
            $firewall = $request->attributes->get(self::ATTRIBUTE_FIREWALL);
            if (!\is_string($firewall) || '' === $firewall) {
                throw new \LogicException(\sprintf('The "_firewall" route default must be a non-empty string, "%s" given.', get_debug_type($firewall)));
            }

            $contextId = self::FIREWALL_CONTEXT_PREFIX.$firewall;
            foreach ($this->map as $mapContextId => $requestMatcher) {
                if ($mapContextId === $contextId) {
                    $request->attributes->set(self::ATTRIBUTE_FIREWALL_CONTEXT, $contextId);

                    return $this->container->get($contextId);
                }
            }

            // falling back to the request matchers would silently authenticate with another firewall
            throw new \LogicException(\sprintf('Invalid firewall "%s" requested by the route: no firewall with this name is configured.', $firewall));
        }

        if ($request->attributes->has(self::ATTRIBUTE_FIREWALL_CONTEXT)) {
            $storedContextId = $request->attributes->get(self::ATTRIBUTE_FIREWALL_CONTEXT);
            foreach ($this->map as $contextId => $requestMatcher) {
                if ($contextId === $storedContextId) {
                    return $this->container->get($contextId);
                }
            }

            $request->attributes->remove(self::ATTRIBUTE_FIREWALL_CONTEXT);
        }

        foreach ($this->map as $contextId => $requestMatcher) {
            if (null === $requestMatcher || $requestMatcher->matches($request)) {
                $request->attributes->set(self::ATTRIBUTE_FIREWALL_CONTEXT, $contextId);

                return $this->container->get($contextId);
            }
        }

        return null;
    }
}
