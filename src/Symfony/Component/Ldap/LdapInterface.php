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
    public const ESCAPE_FILTER = 0x01;
    public const ESCAPE_DN = 0x02;

    /**
     * Return a connection bound to the ldap.
     *
     * @return void
     *
     * @throws ConnectionException if dn / password could not be bound
     */
    public function bind(?string $dn = null, #[\SensitiveParameter] ?string $password = null);

    /**
     * Queries a ldap server for entries matching the given criteria.
     */
    public function query(string $dn, string $query, array $options = []): QueryInterface;

    public function getEntryManager(): EntryManagerInterface;

    /**
     * Escape a string for use in an LDAP filter or DN.
     */
    public function escape(string $subject, string $ignore = '', int $flags = 0): string;
}
