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

use Symfony\Component\Ldap\Exception\ConnectionException;

/**
 * Base Ldap interface.
 *
 * This interface is here for reusability in the BC layer,
 * and will be merged in LdapInterface in Symfony 4.0.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @internal
 */
interface BaseLdapInterface
{
    /**
     * Return a connection bound to the ldap.
     *
     * @param string $dn       A LDAP dn
     * @param string $password A password
     *
     * @throws ConnectionException If dn / password could not be bound.
     */
    public function bind($dn = null, $password = null);

    /**
     * Escape a string for use in an LDAP filter or DN.
     *
     * @param string $subject
     * @param string $ignore
     * @param int    $flags
     *
     * @return string
     */
    public function escape($subject, $ignore = '', $flags = 0);
}
