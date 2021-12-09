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
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides basic functionality for services mapped by the firewall name
 * in a container locator.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
trait FirewallAwareTrait
{
    private ContainerInterface $locator;
    private RequestStack $requestStack;
    private FirewallMap $firewallMap;

    private function getForFirewall(): object
    {
        $serviceIdentifier = str_replace('FirewallAware', '', static::class);
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            throw new \LogicException('Cannot determine the correct '.$serviceIdentifier.' to use: there is no active Request and so, the firewall cannot be determined. Try using a specific '.$serviceIdentifier.' service.');
        }

        $firewall = $this->firewallMap->getFirewallConfig($request);
        if (!$firewall) {
            throw new \LogicException('No '.$serviceIdentifier.' found as the current route is not covered by a firewall.');
        }

        $firewallName = $firewall->getName();
        if (!$this->locator->has($firewallName)) {
            $message = 'No '.$serviceIdentifier.' found for this firewall.';
            if (\defined(static::class.'::FIREWALL_OPTION')) {
                $message .= sprintf('Did you forget to add a "'.static::FIREWALL_OPTION.'" key under your "%s" firewall?', $firewallName);
            }

            throw new \LogicException($message);
        }

        return $this->locator->get($firewallName);
    }
}
