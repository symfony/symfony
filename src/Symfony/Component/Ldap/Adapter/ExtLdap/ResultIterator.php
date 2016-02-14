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

/**
 * @author Charles Sarrazin <charles@sarraz.in>
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
        $dn = ldap_get_dn($this->connection, $this->current);

        return new Entry($dn, $attributes);
    }

    /**
     * Sets the cursor to the next entry.
     */
    public function next()
    {
        $this->current = ldap_next_entry($this->connection, $this->current);
        ++$this->key;
    }

    /**
     * Returns the current key.
     *
     * @return int
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * Checks whether the current entry is valid or not.
     *
     * @return bool
     */
    public function valid()
    {
        return false !== $this->current;
    }

    /**
     * Rewinds the iterator to the first entry.
     */
    public function rewind()
    {
        $this->current = ldap_first_entry($this->connection, $this->search);
    }
}
