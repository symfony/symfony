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
    private $container;
    private $map;
    private $contexts;

    public function __construct(ContainerInterface $container, iterable $map)
    {
        $this->container = $container;
        $this->map = $map;
        $this->contexts = new \SplObjectStorage();
    }

    public function getListeners(Request $request)
    {
        $context = $this->getFirewallContext($request);

        if (null === $context) {
            return array(array(), null, null);
        }

        return array($context->getListeners(), $context->getExceptionListener(), $context->getLogoutListener());
    }

    /**
     * @return FirewallConfig|null
     */
    public function getFirewallConfig(Request $request)
    {
        $context = $this->getFirewallContext($request);

        if (null === $context) {
            return;
        }

        return $context->getConfig();
    }

    /**
     * @return FirewallContext
     */
    private function getFirewallContext(Request $request)
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

                return $this->container->get($contextId);
            }
        }
    }
}
