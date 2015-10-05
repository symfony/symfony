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

use Symfony\Component\Ldap\Exception\LdapException;

class Result implements \IteratorAggregate
{
    private $connection;
    private $search;

    public function __construct($connection, $dn, $query, $options)
    {
        $this->connection = $connection;
        $this->search = ldap_search($connection, $dn, $query, $options['filter'], $options['attrsOnly'], $options['maxItems'], $options['timeout'], $options['deref']);

        if (false === $this->search) {
            throw new LdapException(sprintf('Could not complete search with dn "%s", query "%s" and filters "%s"', $dn, $query, implode(',', $options['filter'])));
        }
    }

    public function all()
    {
        $infos = ldap_get_entries($this->connection, $this->search);

        if (0 === $infos['count']) {
            return array();
        }

        unset($infos['count']);

        return $infos;
    }

    public function getIterator()
    {
        return new ResultIterator($this->connection, $this->search);
    }
}
