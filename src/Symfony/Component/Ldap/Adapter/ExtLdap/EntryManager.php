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

/**
 * @author Charles Sarrazin <charles@sarraz.in>
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
        $con = $this->connection->getResource();

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
        $con = $this->connection->getResource();

        if (!@ldap_modify($con, $entry->getDn(), $entry->getAttributes())) {
            throw new LdapException(sprintf('Could not update entry "%s": %s', $entry->getDn(), ldap_error($con)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove(Entry $entry)
    {
        $con = $this->connection->getResource();

        if (!@ldap_delete($con, $entry->getDn())) {
            throw new LdapException(sprintf('Could not remove entry "%s": %s', $entry->getDn(), ldap_error($con)));
        }
    }
}
