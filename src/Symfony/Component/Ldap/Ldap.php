<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap;

use Symfony\Component\Ldap\Connection\Connection;
use Symfony\Component\Ldap\Connection\ConnectionInterface;
use Symfony\Component\Ldap\Search\Query;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Ldap implements LdapInterface
{
    private $connection;

    /**
     * Constructor.
     *
     * @param ConnectionInterface $connection A configured Ldap connection
     */
    public function __construct(ConnectionInterface $connection = null)
    {
        $this->connection = $connection ?: new Connection();
    }

    /**
     * {@inheritdoc}
     */
    public function bind($dn = null, $password = null)
    {
        $this->connection->bind($dn, $password);
    }

    /**
     * {@inheritdoc}
     */
    public function query($dn, $query, array $options = array())
    {
        $search = new Query($this->connection, $dn, $query, $options);

        return $search->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function escape($subject, $ignore = '', $flags = 0)
    {
        $value = ldap_escape($subject, $ignore, $flags);

        // Per RFC 4514, leading/trailing spaces should be encoded in DNs, as well as carriage returns.
        if ((int) $flags & LDAP_ESCAPE_DN) {
            if (!empty($value) && $value[0] === ' ') {
                $value = '\\20'.substr($value, 1);
            }
            if (!empty($value) && $value[strlen($value) - 1] === ' ') {
                $value = substr($value, 0, -1).'\\20';
            }
            $value = str_replace("\r", '\0d', $value);
        }

        return $value;
    }
}
