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

    /**
     * @return int
     */
    public function count()
    {
        $con = $this->connection->getResource();
        $searches = $this->search->getResources();
        $count = 0;
        foreach ($searches as $search) {
            $searchCount = ldap_count_entries($con, $search);
            if (false === $searchCount) {
                throw new LdapException(sprintf('Error while retrieving entry count: %s.', ldap_error($con)));
            }
            $count += $searchCount;
        }

        return $count;
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        if (0 === $this->count()) {
            return;
        }

        $con = $this->connection->getResource();
        $searches = $this->search->getResources();
        foreach ($searches as $search) {
            $current = ldap_first_entry($con, $search);

            if (false === $current) {
                throw new LdapException(sprintf('Could not rewind entries array: %s.', ldap_error($con)));
            }

            yield $this->getSingleEntry($con, $current);

            while (false !== $current = ldap_next_entry($con, $current)) {
                yield $this->getSingleEntry($con, $current);
            }
        }
    }

    /**
     * @return bool
     */
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

    private function getSingleEntry($con, $current): Entry
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

    private function cleanupAttributes(array $entry): array
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
