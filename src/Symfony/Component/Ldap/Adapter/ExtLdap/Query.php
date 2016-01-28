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

use Symfony\Component\Ldap\Adapter\AbstractQuery;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Query extends AbstractQuery
{
    /** @var  Connection */
    protected $connection;

    /** @var resource */
    private $search;

    public function __construct(Connection $connection, $dn, $query, array $options = array())
    {
        parent::__construct($connection, $dn, $query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // If the connection is not bound, then we try an anonymous bind.
        if (!$this->connection->isBound()) {
            $this->connection->bind();
        }

        $con = $this->connection->getResource();

        $this->search = ldap_search(
            $con,
            $this->dn,
            $this->query,
            $this->options['filter'],
            $this->options['attrsOnly'],
            $this->options['maxItems'],
            $this->options['timeout'],
            $this->options['deref']
        );

        if (!$this->search) {
            throw new LdapException(sprintf('Could not complete search with dn "%s", query "%s" and filters "%s"', $this->dn, $this->query, implode(',', $this->options['filter'])));
        };

        return new Collection($this->connection, $this);
    }

    /**
     * Returns a LDAP search resource.
     *
     * @return resource
     */
    public function getResource()
    {
        return $this->search;
    }
}
