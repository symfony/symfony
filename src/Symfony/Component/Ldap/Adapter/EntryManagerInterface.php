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
 * Entry manager interface.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 * @author Kevin Schuurmans <kevin.schuurmans@freshheads.com>
 *
 * The move() methods must be added to the interface in Symfony 5.0
 *
 * @method void move(Entry $entry, string $newParent) Moves an entry on the Ldap server
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
     * Renames an entry on the Ldap server.
     *
     * @param string $newRdn
     * @param bool   $removeOldRdn
     */
    public function rename(Entry $entry, $newRdn, $removeOldRdn = true);

    /**
     * Removes an entry from the Ldap server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function remove(Entry $entry);
}
