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
 * AdvancedUserInterface adds status flags to a regular account.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface AdvancedUserInterface extends UserInterface
{
    /**
     * Checks whether the user's account has expired.
     *
     * @return boolean true if the user's account is non expired, false otherwise
     */
    function isAccountNonExpired();

    /**
     * Checks whether the user is locked.
     *
     * @return boolean true if the user is not locked, false otherwise
     */
    function isAccountNonLocked();

    /**
     * Checks whether the user's credentials (password) has expired.
     *
     * @return boolean true if the user's credentials are non expired, false otherwise
     */
    function isCredentialsNonExpired();

    /**
     * Checks whether the user is enabled.
     *
     * @return boolean true if the user is enabled, false otherwise
     */
    function isEnabled();
}
