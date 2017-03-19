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

use Symfony\Component\Ldap\Adapter\EntryManagerInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;

/**
 * Ldap interface.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 */
interface LdapInterface
{
    const ESCAPE_FILTER = 0x01;
    const ESCAPE_DN = 0x02;

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
     * Queries a ldap server for entries matching the given criteria.
     *
     * @param string $dn
     * @param string $query
     * @param array  $options
     *
     * @return QueryInterface
     */
    public function query($dn, $query, array $options = array());

    /**
     * @return EntryManagerInterface
     */
    public function getEntryManager();

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
