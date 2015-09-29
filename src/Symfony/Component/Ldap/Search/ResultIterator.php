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

class ResultIterator implements \Iterator
{
    private $connection;
    private $search;
    private $current;
    private $key;

    public function __construct($connection, $search)
    {
        $this->connection = $connection;
        $this->search = $search;
    }

    public function current()
    {
        $attributes = ldap_get_attributes($this->connection, $this->current);
        $count = $attributes['count'];
        $dn = ldap_get_dn($this->connection, $this->current);
        unset($attributes['count']);

        return array(
            'dn' => $dn,
            'count' => $count,
            'attributes' => $attributes,
        );
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
    }
}
