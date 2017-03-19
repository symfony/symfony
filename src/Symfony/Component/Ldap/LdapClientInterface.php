<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap;

/**
 * Ldap interface.
 *
 * This interface is used for the BC layer with branch 2.8 and 3.0.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @deprecated You should use LdapInterface instead
 */
interface LdapClientInterface extends LdapInterface
{
    /**
     * Find a username into ldap connection.
     *
     * @param string $dn
     * @param string $query
     * @param mixed  $filter
     *
     * @return array|null
     */
    public function find($dn, $query, $filter = '*');
}
