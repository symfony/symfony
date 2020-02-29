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

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\LogicException;

/**
 * A decorator that delegates all method calls to the authenticator
 * manager of the current firewall.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class FirewallAwareAuthenticatorManager implements AuthenticationManagerInterface
{
    private $firewallMap;
    private $authenticatorManagers;
    private $requestStack;

    public function __construct(FirewallMap $firewallMap, ServiceLocator $authenticatorManagers, RequestStack $requestStack)
    {
        $this->firewallMap = $firewallMap;
        $this->authenticatorManagers = $authenticatorManagers;
        $this->requestStack = $requestStack;
    }

    public function authenticate(TokenInterface $token)
    {
        $firewallConfig = $this->firewallMap->getFirewallConfig($this->requestStack->getMasterRequest());
        if (null === $firewallConfig) {
            throw new LogicException('Cannot call authenticate on this request, as it is not behind a firewall.');
        }

        return $this->authenticatorManagers->get($firewallConfig->getName())->authenticate($token);
    }
}
