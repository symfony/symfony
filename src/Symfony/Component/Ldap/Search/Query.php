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
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Query implements QueryInterface
{
    private $connection;
    private $dn;
    private $query;
    private $options;
    private $search;

    /**
     * Constructor.
     *
     * @param ConnectionInterface $connection
     * @param string              $dn
     * @param string              $query
     * @param array               $options
     */
    public function __construct(ConnectionInterface $connection, $dn, $query, array $options = array())
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(array(
            'filter' => '*',
            'maxItems' => 0,
            'sizeLimit' => 0,
            'timeout' => 0,
            'deref' => LDAP_DEREF_NEVER,
            'attrsOnly' => 0,
        ));
        $resolver->setAllowedValues('deref', array(LDAP_DEREF_ALWAYS, LDAP_DEREF_NEVER, LDAP_DEREF_FINDING, LDAP_DEREF_SEARCHING));
        $resolver->setNormalizer('filter', function (Options $options, $value) {
            return is_array($value) ? $value : array($value);
        });

        $this->connection = $connection;
        $this->dn = $dn;
        $this->query = $query;
        $this->options = $resolver->resolve($options);
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
        if (!$this->search = ldap_search($con, $this->dn, $this->query, $this->options['filter'], $this->options['attrsOnly'], $this->options['maxItems'], $this->options['timeout'], $this->options['deref'])) {
            throw new LdapException(sprintf('Could not complete search with dn "%s", query "%s" and filters "%s"', $this->dn, $this->query, implode(',', $this->options['filter'])));
        };

        return new Collection($this->connection, $this);
    }

    public function getResource()
    {
        return $this->search;
    }
}
