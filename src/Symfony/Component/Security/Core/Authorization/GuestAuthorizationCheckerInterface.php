<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Extends the user authorization checker with support for guests.
 *
 * @author Nate Wiebe <nate@northern.co>
 * @author Andreas Schempp <andreas.schempp@terminal42.ch>
 */
interface GuestAuthorizationCheckerInterface extends UserAuthorizationCheckerInterface
{
    /**
     * Checks if the attribute is granted against the user and optionally supplied subject.
     *
     * A null user means a guest: the attribute is voted on with no user and no roles.
     *
     * @param mixed               $attribute      A single attribute to vote on (can be of any type, string and instance of Expression are supported by the core)
     * @param AccessDecision|null $accessDecision Should be used to explain the decision
     */
    public function isGrantedForUser(?UserInterface $user, mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool;
}
