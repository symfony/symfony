<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

/**
 * Adds application-specific checks to the built-in detection of user changes,
 * which decides whether a user restored from the session must be re-authenticated.
 *
 * Unlike EquatableInterface, this does not replace the built-in checks: password,
 * roles and identifier comparisons keep running, and the user is deauthenticated
 * as soon as either the built-in checks or the additional ones find a change.
 *
 * @see EquatableInterface to replace the built-in checks instead of adding to them
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
interface UserChangeAwareInterface
{
    /**
     * Reports changes the built-in checks cannot know about, e.g. an administrator
     * disabling the user while their session is still active.
     *
     * $this is the user restored from the session, $refreshedUser the one that was
     * just reloaded from the user provider.
     *
     * @return bool true to deauthenticate the user, false to leave the decision to
     *              the built-in checks
     */
    public function hasAdditionalChanges(UserInterface $refreshedUser): bool;
}
