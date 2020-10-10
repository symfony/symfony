<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\LoginLink;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkDetails;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * Decorates the login link handler for the current firewall.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class FirewallAwareLoginLinkHandler implements LoginLinkHandlerInterface
{
    private $firewallMap;
    private $loginLinkHandlerLocator;
    private $requestStack;

    public function __construct(FirewallMap $firewallMap, ContainerInterface $loginLinkHandlerLocator, RequestStack $requestStack)
    {
        $this->firewallMap = $firewallMap;
        $this->loginLinkHandlerLocator = $loginLinkHandlerLocator;
        $this->requestStack = $requestStack;
    }

    public function createLoginLink(UserInterface $user): LoginLinkDetails
    {
        return $this->getLoginLinkHandler()->createLoginLink($user);
    }

    public function consumeLoginLink(Request $request): UserInterface
    {
        return $this->getLoginLinkHandler()->consumeLoginLink($request);
    }

    private function getLoginLinkHandler(): LoginLinkHandlerInterface
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            throw new \LogicException('Cannot determine the correct LoginLinkHandler to use: there is no active Request and so, the firewall cannot be determined. Try using the specific login link handler service.');
        }

        $firewall = $this->firewallMap->getFirewallConfig($request);
        if (!$firewall) {
            throw new \LogicException('No login link handler found as the current route is not covered by a firewall.');
        }

        $firewallName = $firewall->getName();
        if (!$this->loginLinkHandlerLocator->has($firewallName)) {
            throw new \LogicException(sprintf('No login link handler found. Did you add a login_link key under your "%s" firewall?', $firewallName));
        }

        return $this->loginLinkHandlerLocator->get($firewallName);
    }
}
