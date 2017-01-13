<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter\ExtLdap;

use Symfony\Component\Ldap\Adapter\EntryManagerInterface;
use Symfony\Component\Ldap\Adapter\RenameEntryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 */
class EntryManager implements EntryManagerInterface, RenameEntryInterface
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function add(Entry $entry)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_add($con, $entry->getDn(), $entry->getAttributes())) {
            throw new LdapException(sprintf('Could not add entry "%s": %s', $entry->getDn(), ldap_error($con)));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Entry $entry)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_modify($con, $entry->getDn(), $entry->getAttributes())) {
            throw new LdapException(sprintf('Could not update entry "%s": %s', $entry->getDn(), ldap_error($con)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove(Entry $entry)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_delete($con, $entry->getDn())) {
            throw new LdapException(sprintf('Could not remove entry "%s": %s', $entry->getDn(), ldap_error($con)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename(Entry $entry, $newRdn, $removeOldRdn = true)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_rename($con, $entry->getDn(), $newRdn, null, $removeOldRdn)) {
            throw new LdapException(sprintf('Could not rename entry "%s" to "%s": %s', $entry->getDn(), $newRdn, ldap_error($con)));
        }
    }

    /**
     * Get the connection resource, but first check if the connection is bound.
     */
    private function getConnectionResource()
    {
        // If the connection is not bound, throw an exception. Users should use an explicit bind call first.
        if (!$this->connection->isBound()) {
            throw new NotBoundException('Query execution is not possible without binding the connection first.');
        }

        return $this->connection->getResource();
    }
}
