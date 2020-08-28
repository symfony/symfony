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
 * Passport badges allow to add more information to a passport (e.g. a CSRF token).
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
interface BadgeInterface
{
    /**
     * Checks if this badge is resolved by the security system.
     *
     * After authentication, all badges must return `true` in this method in order
     * for the authentication to succeed.
     */
    public function isResolved(): bool;
}
