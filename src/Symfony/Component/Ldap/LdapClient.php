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
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class LdapClient implements LdapClientInterface
{
    private $connection;
    private $charmaps;

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
        if (!$this->connection->isConnected()) {
            $this->connection->connect();
        }

        $this->connection->bind($dn, $password);
    }

    /**
     * {@inheritdoc}
     */
    public function find($dn, $query, array $options = array())
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(array(
            'filter' => '*',
            'maxItems' => 0,
            'sizeLimit' => 0,
            'timeout' => 0,
            'deref' => LDAP_DEREF_NEVER,
        ));
        $resolver->setNormalizer('filter', function (Options $options, $value) {
            return is_array($value) ? $value : array($value);
        });
        $options = $resolver->resolve($options);

        // If the connection is not bound, then we try an anonymous bind.
        if (!$this->connection->isBound()) {
            $this->connection->bind();
        }

        $connection = $this->connection->getConnection();

        $search = ldap_search($connection, $dn, $query, $options['filter'], $options['attrsOnly'], $options['maxItems'], $options['timeout'], $options['deref']);

        if (false === $search) {
            throw new LdapException(sprintf('Could not complete search with DN "%s", query "%s" and filters "%s"', $dn, $query, implode(',', $options['filter'])));
        }

        $infos = ldap_get_entries($connection, $search);

        if (0 === $infos['count']) {
            return;
        }

        return $infos;
    }

    /**
     * {@inheritdoc}
     */
    public function escape($subject, $ignore = '', $flags = 0)
    {
        return ldap_escape($subject, $ignore, $flags);
    }
}
