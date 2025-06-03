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
 * Provides generator functions for the impersonation urls.
 *
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 * @author Damien Fayet <damienf1521@gmail.com>
 */
class ImpersonateUrlGenerator
{
    public function __construct(
        private RequestStack $requestStack,
        private FirewallMap $firewallMap,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function generateImpersonationPath(string $identifier): string
    {
        return $this->buildPath(null, $identifier);
    }

    public function generateImpersonationUrl(string $identifier): string
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            return '';
        }

        return $request->getUriForPath($this->buildPath(null, $identifier));
    }

    public function generateExitPath(?string $targetUri = null): string
    {
        return $this->buildPath($targetUri);
    }

    public function generateExitUrl(?string $targetUri = null): string
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            return '';
        }

        return $request->getUriForPath($this->buildPath($targetUri));
    }

    private function isImpersonatedUser(): bool
    {
        return $this->tokenStorage->getToken() instanceof SwitchUserToken;
    }

    private function buildPath(?string $targetUri = null, string $identifier = SwitchUserListener::EXIT_VALUE): string
    {
        if (null === ($request = $this->requestStack->getCurrentRequest())) {
            return '';
        }

        if (!$this->isImpersonatedUser() && SwitchUserListener::EXIT_VALUE == $identifier) {
            return '';
        }

        if (null === $switchUserConfig = $this->firewallMap->getFirewallConfig($request)->getSwitchUser()) {
            throw new \LogicException('Unable to generate the impersonate URLs without a firewall configured for the user switch.');
        }

        $targetUri ??= $request->getRequestUri();

        $targetUri .= (str_contains($targetUri, '?') ? '&' : '?').http_build_query([$switchUserConfig['parameter'] => $identifier], '', '&');

        return $targetUri;
    }
}
