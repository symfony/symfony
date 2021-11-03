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
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;
use Symfony\Component\Ldap\Exception\UpdateOperationException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 */
class EntryManager implements EntryManagerInterface
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
            throw new LdapException(sprintf('Could not add entry "%s": ', $entry->getDn()).ldap_error($con), ldap_errno($con));
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
            throw new LdapException(sprintf('Could not update entry "%s": ', $entry->getDn()).ldap_error($con), ldap_errno($con));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove(Entry $entry)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_delete($con, $entry->getDn())) {
            throw new LdapException(sprintf('Could not remove entry "%s": ', $entry->getDn()).ldap_error($con), ldap_errno($con));
        }
    }

    /**
     * Adds values to an entry's multi-valued attribute from the LDAP server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function addAttributeValues(Entry $entry, string $attribute, array $values)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_mod_add($con, $entry->getDn(), [$attribute => $values])) {
            throw new LdapException(sprintf('Could not add values to entry "%s", attribute "%s": ', $entry->getDn(), $attribute).ldap_error($con), ldap_errno($con));
        }
    }

    /**
     * Removes values from an entry's multi-valued attribute from the LDAP server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function removeAttributeValues(Entry $entry, string $attribute, array $values)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_mod_del($con, $entry->getDn(), [$attribute => $values])) {
            throw new LdapException(sprintf('Could not remove values from entry "%s", attribute "%s": ', $entry->getDn(), $attribute).ldap_error($con), ldap_errno($con));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename(Entry $entry, string $newRdn, bool $removeOldRdn = true)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_rename($con, $entry->getDn(), $newRdn, null, $removeOldRdn)) {
            throw new LdapException(sprintf('Could not rename entry "%s" to "%s": ', $entry->getDn(), $newRdn).ldap_error($con), ldap_errno($con));
        }
    }

    /**
     * Moves an entry on the Ldap server.
     *
     * @throws NotBoundException if the connection has not been previously bound
     * @throws LdapException     if an error is thrown during the rename operation
     */
    public function move(Entry $entry, string $newParent)
    {
        $con = $this->getConnectionResource();
        $rdn = $this->parseRdnFromEntry($entry);
        // deleteOldRdn does not matter here, since the Rdn will not be changing in the move.
        if (!@ldap_rename($con, $entry->getDn(), $rdn, $newParent, true)) {
            throw new LdapException(sprintf('Could not move entry "%s" to "%s": ', $entry->getDn(), $newParent).ldap_error($con), ldap_errno($con));
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

    /**
     * @param iterable<int, UpdateOperation> $operations An array or iterable of UpdateOperation instances
     *
     * @throws UpdateOperationException in case of an error
     */
    public function applyOperations(string $dn, iterable $operations): void
    {
        $operationsMapped = [];
        foreach ($operations as $modification) {
            $operationsMapped[] = $modification->toArray();
        }

        $con = $this->getConnectionResource();
        if (!@ldap_modify_batch($con, $dn, $operationsMapped)) {
            throw new UpdateOperationException(sprintf('Error executing UpdateOperation on "%s": ', $dn).ldap_error($con), ldap_errno($con));
        }
    }

    private function parseRdnFromEntry(Entry $entry): string
    {
        if (!preg_match('/(^[^,\\\\]*(?:\\\\.[^,\\\\]*)*),/', $entry->getDn(), $matches)) {
            throw new LdapException(sprintf('Entry "%s" malformed, could not parse RDN.', $entry->getDn()));
        }

        return $matches[1];
    }
}
