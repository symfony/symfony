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

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * LdapUserProviderInterface.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface LdapUserProviderInterface extends UserProviderInterface
{
    /**
     * Loads the user for the given username and password.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string   $username The username
     * @param password $username The password
     *
     * @return UserInterface
     *
     * @see UsernameNotFoundException
     *
     * @throws UsernameNotFoundException if the user is not found
     *
     */
    public function loadUserByUsernameAndPassword($username, $password);

    /**
     * Return roles for current user
     *
     * @return array roles
     */
    public function loadRoles();
}
