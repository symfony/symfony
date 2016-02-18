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

use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @internal
 */
class ResultIterator implements \Iterator
{
    private $connection;
    private $search;
    private $current;
    private $key;

    public function __construct(Connection $connection, Query $search)
    {
        $this->connection = $connection->getResource();
        $this->search = $search->getResource();
    }

    /**
     * Fetches the current entry.
     *
     * @return Entry
     */
    public function current()
    {
        $attributes = ldap_get_attributes($this->connection, $this->current);

        if (false === $attributes) {
            throw new LdapException(sprintf('Could not fetch attributes: %s', ldap_error($this->connection)));
        }

        $dn = ldap_get_dn($this->connection, $this->current);

        if (false === $dn) {
            throw new LdapException(sprintf('Could not fetch DN: %s', ldap_error($this->connection)));
        }

        return new Entry($dn, $attributes);
    }

    public function next()
    {
        $this->current = ldap_next_entry($this->connection, $this->current);
        ++$this->key;
    }

    public function key()
    {
        return $this->key;
    }

    public function valid()
    {
        return false !== $this->current;
    }

    public function rewind()
    {
        $this->current = ldap_first_entry($this->connection, $this->search);

        if (false === $this->current) {
            throw new LdapException(sprintf('Could not rewind entries array: %s', ldap_error($this->connection)));
        }
    }
}
