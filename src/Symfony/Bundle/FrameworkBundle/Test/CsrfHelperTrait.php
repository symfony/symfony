<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * Provides methods to deal with CSRF tokens in a stateless container.
 */
trait CsrfHelperTrait
{
    use SessionHelperTrait;

    private function getCsrfToken(KernelBrowser $client, string $tokenId): CsrfToken
    {
        return $this->callInRequestContext($client, function () use ($tokenId) {
            return static::getContainer()->get('security.csrf.token_manager')->getToken($tokenId);
        });
    }

    private function removeCsrfToken(KernelBrowser $client, string $tokenId): CsrfToken
    {
        return $this->callInRequestContext($client, function () use ($tokenId) {
            return static::getContainer()->get('security.csrf.token_manager')->removeToken($tokenId);
        });
    }

    private function isCsrfTokenValid(KernelBrowser $client, CsrfToken $token): bool
    {
        return $this->callInRequestContext($client, function () use ($token) {
            return static::getContainer()->get('security.csrf.token_manager')->isTokenValid($token);
        });
    }
}
