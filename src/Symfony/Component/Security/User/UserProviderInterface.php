<?php

namespace Symfony\Component\Security\User;

use Symfony\Component\Security\Exception\UsernameNotFoundException;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * UserProviderInterface is the implementation that all user provider must
 * implement.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface UserProviderInterface
{
    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param  string $username The username
     *
     * @return AccountInterface A user instance
     *
     * @throws UsernameNotFoundException if the user is not found
     */
     function loadUserByUsername($username);
}
