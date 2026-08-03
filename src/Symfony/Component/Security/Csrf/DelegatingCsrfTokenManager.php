<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf;

use Psr\Container\ContainerInterface;

/**
 * This CSRF token manager hands each token id over to the manager dedicated to it, if any.
 *
 * Token ids with no dedicated manager are delegated to the decorated one, so that a token is
 * always minted by the manager that validates it, wherever it is asked for.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class DelegatingCsrfTokenManager implements CsrfTokenManagerInterface
{
    /**
     * @param ContainerInterface $csrfTokenManagers Locator of CsrfTokenManagerInterface instances, keyed by token id
     */
    public function __construct(
        private CsrfTokenManagerInterface $fallbackCsrfTokenManager,
        private ContainerInterface $csrfTokenManagers,
    ) {
    }

    public function getToken(string $tokenId): CsrfToken
    {
        return $this->getCsrfTokenManager($tokenId)->getToken($tokenId);
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        return $this->getCsrfTokenManager($tokenId)->refreshToken($tokenId);
    }

    public function removeToken(string $tokenId): ?string
    {
        return $this->getCsrfTokenManager($tokenId)->removeToken($tokenId);
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        return $this->getCsrfTokenManager($token->getId())->isTokenValid($token);
    }

    private function getCsrfTokenManager(string $tokenId): CsrfTokenManagerInterface
    {
        return $this->csrfTokenManagers->has($tokenId) ? $this->csrfTokenManagers->get($tokenId) : $this->fallbackCsrfTokenManager;
    }
}
