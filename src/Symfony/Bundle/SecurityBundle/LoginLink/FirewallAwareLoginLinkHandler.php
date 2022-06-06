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
use Symfony\Bundle\SecurityBundle\Security\FirewallAwareTrait;
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
    use FirewallAwareTrait;

    private const FIREWALL_OPTION = 'login_link';

    public function __construct(FirewallMap $firewallMap, ContainerInterface $loginLinkHandlerLocator, RequestStack $requestStack)
    {
        $this->firewallMap = $firewallMap;
        $this->locator = $loginLinkHandlerLocator;
        $this->requestStack = $requestStack;
    }

    public function createLoginLink(UserInterface $user, Request $request = null): LoginLinkDetails
    {
        return $this->getForFirewall()->createLoginLink($user, $request);
    }

    public function consumeLoginLink(Request $request): UserInterface
    {
        return $this->getForFirewall()->consumeLoginLink($request);
    }
}
