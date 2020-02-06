<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\Security\Guard\AuthenticatorInterface as GuardAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface as CoreAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PreAuthenticationToken;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @internal
 */
trait AuthenticatorManagerTrait
{
    /**
     * @return CoreAuthenticatorInterface|GuardAuthenticatorInterface|null
     */
    private function findOriginatingAuthenticator(PreAuthenticationToken $token)
    {
        // find the *one* Authenticator that this token originated from
        foreach ($this->authenticators as $key => $authenticator) {
            // get a key that's unique to *this* authenticator
            // this MUST be the same as AuthenticatorManagerListener
            $uniqueAuthenticatorKey = $this->getAuthenticatorKey($key);

            if ($uniqueAuthenticatorKey === $token->getAuthenticatorKey()) {
                return $authenticator;
            }
        }

        // no matching authenticator found
        return null;
    }

    abstract protected function getAuthenticatorKey(string $key): string;
}
