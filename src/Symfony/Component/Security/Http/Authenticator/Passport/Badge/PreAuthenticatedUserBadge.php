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

use Symfony\Component\Security\Http\Authenticator\AbstractPreAuthenticatedAuthenticator;

/**
 * Marks the authentication as being pre-authenticated.
 *
 * This disables pre-authentication user checkers.
 *
 * @see AbstractPreAuthenticatedAuthenticator
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class PreAuthenticatedUserBadge implements BadgeInterface
{
    public function isResolved(): bool
    {
        return true;
    }
}
