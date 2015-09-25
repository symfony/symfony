<?php

namespace Symfony\Component\Ldap;

use Symfony\Component\Ldap\Exception\ConnectionException;

/**
 * Ldap interface.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 */
interface LdapClientInterface
{
    const LDAP_ESCAPE_FILTER = 0x01;
    const LDAP_ESCAPE_DN = 0x02;

    /**
     * Return a connection bound to the ldap.
     *
     * @param string $dn       A LDAP dn
     * @param string $password A password
     *
     * @throws ConnectionException If dn / password could not be bound.
     */
    public function bind($dn = null, $password = null);

    /*
     * Find a username into ldap connection.
     *
     * @param string $dn
     * @param string $query
     * @param mixed  $filter
     *
     * @return array|null
     */
    public function find($dn, $query, $filter = '*');

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
