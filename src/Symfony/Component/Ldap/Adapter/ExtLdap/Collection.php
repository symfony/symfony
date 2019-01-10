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

use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Collection implements CollectionInterface
{
    private $connection;
    private $search;
    private $entries;

    public function __construct(Connection $connection, Query $search)
    {
        $this->connection = $connection;
        $this->search = $search;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        if (null === $this->entries) {
            $this->entries = iterator_to_array($this->getIterator(), false);
        }

        return $this->entries;
    }

    public function count()
    {
        if (false !== $count = ldap_count_entries($this->connection->getResource(), $this->search->getResource())) {
            return $count;
        }

        throw new LdapException(sprintf('Error while retrieving entry count: %s.', ldap_error($this->connection->getResource())));
    }

    public function getIterator()
    {
        $con = $this->connection->getResource();
        $search = $this->search->getResource();
        $current = ldap_first_entry($con, $search);

        if (0 === $this->count()) {
            return;
        }

        if (false === $current) {
            throw new LdapException(sprintf('Could not rewind entries array: %s.', ldap_error($con)));
        }

        yield $this->getSingleEntry($con, $current);

        while (false !== $current = ldap_next_entry($con, $current)) {
            yield $this->getSingleEntry($con, $current);
        }
    }

    public function offsetExists($offset)
    {
        $this->toArray();

        return isset($this->entries[$offset]);
    }

    public function offsetGet($offset)
    {
        $this->toArray();

        return isset($this->entries[$offset]) ? $this->entries[$offset] : null;
    }

    public function offsetSet($offset, $value)
    {
        $this->toArray();

        $this->entries[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        $this->toArray();

        unset($this->entries[$offset]);
    }

    private function getSingleEntry($con, $current)
    {
        $attributes = ldap_get_attributes($con, $current);

        if (false === $attributes) {
            throw new LdapException(sprintf('Could not fetch attributes: %s.', ldap_error($con)));
        }

        $attributes = $this->cleanupAttributes($attributes);

        $dn = ldap_get_dn($con, $current);

        if (false === $dn) {
            throw new LdapException(sprintf('Could not fetch DN: %s.', ldap_error($con)));
        }

        return new Entry($dn, $attributes);
    }

    private function cleanupAttributes(array $entry)
    {
        $attributes = array_diff_key($entry, array_flip(range(0, $entry['count'] - 1)) + [
                'count' => null,
                'dn' => null,
            ]);
        array_walk($attributes, function (&$value) {
            unset($value['count']);
        });

        return $attributes;
    }
}
