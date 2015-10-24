<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Search;

use Symfony\Component\Ldap\Connection\ConnectionInterface;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Collection implements \Countable, \IteratorAggregate, \ArrayAccess
{
    private $connection;
    private $search;
    private $entries;

    /**
     * Constructor.
     *
     * @param ConnectionInterface $connection
     * @param QueryInterface $search
     */
    public function __construct(ConnectionInterface $connection, QueryInterface $search, array $entries = array())
    {
        $this->connection = $connection;
        $this->search = $search;
        $this->entries = array();
    }

    public function toArray()
    {
        $this->initialize();

        return $this->entries;
    }

    public function count()
    {
        $this->initialize();

        return count($this->entries);
    }

    public function getIterator()
    {
        return new ResultIterator($this->connection, $this->search);
    }

    public function offsetExists($offset)
    {
        $this->initialize();

        return isset($this->entries[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->entries[$offset]) ? $this->entries[$offset] : null;
    }

    public function offsetSet($offset, $value)
    {
        $this->initialize();

        $this->entries[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        $this->initialize();

        unset($this->entries[$offset]);
    }

    private function initialize()
    {
        if (null === $this->entries) {
            return;
        }

        $entries = ldap_get_entries($this->connection->getResource(), $this->search->getResource());

        if (0 === $entries['count']) {
            return array();
        }

        unset($entries['count']);

        $this->entries = array_map(function (array $entry) {
            return new Entry($entry['dn'], $entry['attributes']);
        }, $entries);
    }
}
