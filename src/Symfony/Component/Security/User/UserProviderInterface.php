<?php

namespace Symfony\Component\Security\User;

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
     * Whether this provider is an aggregate of user providers
     *
     * @return Boolean
     */
    function isAggregate();

    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param  string $username The username
     *
     * @return array of the form: array(AccountInterface, string) with the
     *               implementation of AccountInterface, and the name of the provider
     *               that was used to retrieve it
     *
     * @throws UsernameNotFoundException if the user is not found
     */
     function loadUserByUsername($username);

     /**
      * Determines whether this provider supports the given provider name
      *
      * @param string $providerName
      * @return Boolean
      */
     function supports($providerName);
}
