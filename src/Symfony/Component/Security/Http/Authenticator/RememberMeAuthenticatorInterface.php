<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

/**
 * This interface must be extended if the authenticator supports remember me functionality.
 *
 * Remember me cookie will be set if *all* of the following are met:
 *  A) SupportsRememberMe() returns true in the successful authenticator
 *  B) The remember_me key under your firewall is configured
 *  C) The "remember me" functionality is activated. This is usually
 *      done by having a _remember_me checkbox in your form, but
 *      can be configured by the "always_remember_me" and "remember_me_parameter"
 *      parameters under the "remember_me" firewall key
 *  D) The onAuthenticationSuccess method returns a Response object
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface RememberMeAuthenticatorInterface
{
    public function supportsRememberMe(): bool;
}
