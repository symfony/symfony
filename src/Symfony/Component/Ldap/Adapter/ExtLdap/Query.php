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
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 */
class Query extends AbstractQuery
{
    /** @var Connection */
    protected $connection;

    /** @var resource */
    private $search;

    public function __construct(Connection $connection, string $dn, string $query, array $options = array())
    {
        parent::__construct($connection, $dn, $query, $options);
    }

    public function __destruct()
    {
        $con = $this->connection->getResource();
        $this->connection = null;

        if (\in_array($this->search, array(null, false), true)) {
            return;
        }

        $success = ldap_free_result($this->search);
        $this->search = null;

        if (!$success) {
            throw new LdapException(sprintf('Could not free results: %s.', ldap_error($con)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (null === $this->search) {
            // If the connection is not bound, throw an exception. Users should use an explicit bind call first.
            if (!$this->connection->isBound()) {
                throw new NotBoundException('Query execution is not possible without binding the connection first.');
            }

            $con = $this->connection->getResource();

            $funcMap = array(
                QueryInterface::SCOPE_BASE => 'ldap_read',
                QueryInterface::SCOPE_ONE => 'ldap_list',
                QueryInterface::SCOPE_SUB => 'ldap_search',
            );

            if (!\array_key_exists($this->options['scope'], $funcMap)) {
                throw new LdapException(sprintf('Could not search in scope "%s".', $this->options['scope']));
            }

            $func = $funcMap[$this->options['scope']];

            $this->search = @$func(
                $con,
                $this->dn,
                $this->query,
                $this->options['filter'],
                $this->options['attrsOnly'],
                $this->options['maxItems'],
                $this->options['timeout'],
                $this->options['deref']
            );
        }

        if (false === $this->search) {
            throw new LdapException(sprintf('Could not complete search with dn "%s", query "%s" and filters "%s".', $this->dn, $this->query, implode(',', $this->options['filter'])));
        }

        return new Collection($this->connection, $this);
    }

    /**
     * Returns a LDAP search resource.
     *
     * @return resource
     *
     * @internal
     */
    public function getResource()
    {
        return $this->search;
    }
}
