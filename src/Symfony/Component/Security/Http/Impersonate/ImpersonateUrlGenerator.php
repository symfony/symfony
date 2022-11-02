<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Impersonate;

use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;

/**
 * Provides generator functions for the impersonate url exit.
 *
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 * @author Damien Fayet <damienf1521@gmail.com>
 */
class ImpersonateUrlGenerator
{
    private RequestStack $requestStack;
    private TokenStorageInterface $tokenStorage;
    private FirewallMap $firewallMap;

    public function __construct(RequestStack $requestStack, FirewallMap $firewallMap, TokenStorageInterface $tokenStorage)
    {
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
        $this->firewallMap = $firewallMap;
    }

    public function generateExitPath(string $targetUri = null): string
    {
        return $this->buildExitPath($targetUri);
    }

    public function generateExitUrl(string $targetUri = null): string
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            return '';
        }

        return $request->getUriForPath($this->buildExitPath($targetUri));
    }

    private function isImpersonatedUser(): bool
    {
        return $this->tokenStorage->getToken() instanceof SwitchUserToken;
    }

    private function buildExitPath(string $targetUri = null): string
    {
        if (null === ($request = $this->requestStack->getCurrentRequest()) || !$this->isImpersonatedUser()) {
            return '';
        }

        if (null === $switchUserConfig = $this->firewallMap->getFirewallConfig($request)->getSwitchUser()) {
            throw new \LogicException('Unable to generate the impersonate exit URL without a firewall configured for the user switch.');
        }

        $targetUri ??= $request->getRequestUri();

        $targetUri .= (parse_url($targetUri, \PHP_URL_QUERY) ? '&' : '?').http_build_query([$switchUserConfig['parameter'] => SwitchUserListener::EXIT_VALUE], '', '&');

        return $targetUri;
    }
}
