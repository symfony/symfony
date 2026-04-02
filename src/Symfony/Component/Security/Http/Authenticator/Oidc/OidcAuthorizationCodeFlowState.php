<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Oidc;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores and retrieves OIDC authorization code flow parameters (state, nonce, PKCE)
 * in the session, namespaced by firewall name.
 *
 * @author Mathieu Music <music.music@gmail.com>
 */
final class OidcAuthorizationCodeFlowState
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $firewallName,
    ) {
    }

    public function setState(string $state): void
    {
        $this->set('state', $state);
    }

    public function getState(): ?string
    {
        return $this->get('state');
    }

    public function setNonce(string $nonce): void
    {
        $this->set('nonce', $nonce);
    }

    public function getNonce(): ?string
    {
        return $this->get('nonce');
    }

    public function setCodeVerifier(string $codeVerifier): void
    {
        $this->set('code_verifier', $codeVerifier);
    }

    public function getCodeVerifier(): ?string
    {
        return $this->get('code_verifier');
    }

    /**
     * Clears all stored OIDC flow state for this firewall.
     */
    public function clear(): void
    {
        $session = $this->requestStack->getSession();
        $prefix = $this->getSessionPrefix();

        $session->remove($prefix.'state');
        $session->remove($prefix.'nonce');
        $session->remove($prefix.'code_verifier');
    }

    private function set(string $key, string $value): void
    {
        $this->requestStack->getSession()->set($this->getSessionPrefix().$key, $value);
    }

    private function get(string $key): ?string
    {
        return $this->requestStack->getSession()->get($this->getSessionPrefix().$key);
    }

    private function getSessionPrefix(): string
    {
        return '_security.oidc_login.'.$this->firewallName.'.';
    }
}
