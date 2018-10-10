<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Logout;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;

/**
 * Provides generator functions for the impersonate url exit.
 *
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 */
class ImpersonateUrlGenerator
{
    private $requestStack;
    private $router;
    private $tokenStorage;
    private $firewallMap;

    public function __construct(RequestStack $requestStack, UrlGeneratorInterface $router, TokenStorageInterface $tokenStorage = null, FirewallMapInterface $firewallMap)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
        $this->firewallMap = $firewallMap;
    }

    public function getImpersonateExitPath(): string
    {
        return $this->generateImpersonateExitUrl(UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function getImpersonateExitUrl(): string
    {
        return $this->generateImpersonateExitUrl(UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function generateImpersonateExitUrl($referenceType): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $parameters = $request->query;
        $exitPath = null;
        if ($this->firewallMap instanceof FirewallMap) {
            $firewallConfig = $this->firewallMap->getFirewallConfig($request);

            // generate exit impersonation path from current request
            if ($this->isImpersonatedUser() && null !== $switchUserConfig = $firewallConfig->getSwitchUser()) {
                $exitPath = $request->getRequestUri();
                $exitPath .= null === $request->getQueryString() ? '?' : '&';
                $exitPath .= sprintf('%s=%s', urlencode($switchUserConfig['parameter']), SwitchUserListener::EXIT_VALUE);
            }
        }
        if (null === $exitPath) {
            throw new \LogicException('Unable to generate the impersonate exit URL without a path.');
        }

        if ('/' === $exitPath[0]) {
            if (!$this->requestStack) {
                throw new \LogicException('Unable to generate the impersonate exit URL without a RequestStack.');
            }

            $url = UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? $request->getUriForPath($exitPath) : $request->getBaseUrl().$exitPath;

            if (!empty($parameters)) {
                $url .= '?'.http_build_query($parameters);
            }
        } else {
            if (!$this->router) {
                throw new \LogicException('Unable to generate the impersonate exit URL without a Router.');
            }

            $url = $this->router->generate($exitPath, array(), $referenceType);
        }

        return $url;
    }

    private function isImpersonatedUser(): bool
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return false;
        }

        $assignedRoles = $token->getRoles();

        foreach ($assignedRoles as $role) {
            if ($role instanceof SwitchUserRole) {
                return true;
            }
        }
    }
}
