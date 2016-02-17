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

    public function __construct(Connection $connection, Query $search, array $entries = array())
    {
        $this->connection = $connection;
        $this->search = $search;
        $this->entries = array();
    }

    /**
     * {@inheritdoc}
     */
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

        $con = $this->connection->getResource();

        $entries = ldap_get_entries($con, $this->search->getResource());

        if (false === $entries) {
            throw new LdapException(sprintf('Could not load entries: %s', ldap_error($con)));
        }

        if (0 === $entries['count']) {
            return array();
        }

        unset($entries['count']);

        $this->entries = array_map(function (array $entry) {
            $dn = $entry['dn'];
            $attributes = $this->cleanupAttributes($entry);

            return new Entry($dn, $attributes);
        }, $entries);
    }

    private function cleanupAttributes(array $entry = array())
    {
        $attributes = array_diff_key($entry, array_flip(range(0, $entry['count'] - 1)) + array(
                'count' => null,
                'dn' => null,
            ));
        array_walk($attributes, function (&$value) {
            unset($value['count']);
        });

        return $attributes;
    }
}
