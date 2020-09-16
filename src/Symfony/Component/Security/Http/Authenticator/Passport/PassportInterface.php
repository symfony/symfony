<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * A Passport contains all security-related information that needs to be
 * validated during authentication.
 *
 * A passport badge can be used to add any additional information to the
 * passport.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
interface PassportInterface
{
    /**
     * Adds a new security badge.
     *
     * A passport can hold only one instance of the same security badge.
     * This method replaces the current badge if it is already set on this
     * passport.
     *
     * @return $this
     */
    public function addBadge(BadgeInterface $badge): self;

    public function hasBadge(string $badgeFqcn): bool;

    public function getBadge(string $badgeFqcn): ?BadgeInterface;

    /**
     * Checks if all badges are marked as resolved.
     *
     * @throws BadCredentialsException when a badge is not marked as resolved
     */
    public function checkIfCompletelyResolved(): void;
}
