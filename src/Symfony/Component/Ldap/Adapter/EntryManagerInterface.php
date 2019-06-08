<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter;

use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 * @author Kevin Schuurmans <kevin.schuurmans@freshheads.com>
 */
interface EntryManagerInterface
{
    /**
     * Adds a new entry in the Ldap server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function add(Entry $entry);

    /**
     * Updates an entry from the Ldap server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function update(Entry $entry);

    /**
     * Moves an entry on the Ldap server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function move(Entry $entry, string $newParent);

    /**
     * Renames an entry on the Ldap server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function rename(Entry $entry, string $newRdn, bool $removeOldRdn = true);

    /**
     * Removes an entry from the Ldap server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function remove(Entry $entry);
}
