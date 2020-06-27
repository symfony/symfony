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
 * Adds automatic login throttling.
 *
 * This limits the number of failed login attempts over
 * a period of time based on username and IP address.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.2
 */
class LoginThrottlingBadge implements BadgeInterface
{
    private $username;

    /**
     * @param string $username The presented username
     */
    public function __construct(string $username)
    {
        $this->username = $username;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
