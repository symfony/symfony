<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport\Badge;

/**
 * Adds support for remember me to this authenticator.
 *
 * Remember me cookie will be set if *all* of the following are met:
 *  A) This badge is present in the Passport
 *  B) The remember_me key under your firewall is configured
 *  C) The "remember me" functionality is activated. This is usually
 *      done by having a _remember_me checkbox in your form, but
 *      can be configured by the "always_remember_me" and "remember_me_parameter"
 *      parameters under the "remember_me" firewall key
 *  D) The authentication process returns a success Response object
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.1
 */
class RememberMeBadge implements BadgeInterface
{
    public function isResolved(): bool
    {
        return true; // remember me does not need to be explicitly resolved
    }
}
