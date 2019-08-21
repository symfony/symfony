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
use Symfony\Component\Ldap\Exception\NotBoundException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 */
class Query extends AbstractQuery
{
    // As of PHP 7.2, we can use LDAP_CONTROL_PAGEDRESULTS instead of this
    const PAGINATION_OID = '1.2.840.113556.1.4.319';

    /** @var Connection */
    protected $connection;

    /** @var resource[] */
    private $results;

    public function __construct(Connection $connection, string $dn, string $query, array $options = [])
    {
        parent::__construct($connection, $dn, $query, $options);
    }

    public function __destruct()
    {
        $con = $this->connection->getResource();
        $this->connection = null;

        if (null === $this->results) {
            return;
        }

        foreach ($this->results as $result) {
            if (false === $result || null === $result) {
                continue;
            }
            if (!ldap_free_result($result)) {
                throw new LdapException(sprintf('Could not free results: %s.', ldap_error($con)));
            }
        }
        $this->results = null;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (null === $this->results) {
            // If the connection is not bound, throw an exception. Users should use an explicit bind call first.
            if (!$this->connection->isBound()) {
                throw new NotBoundException('Query execution is not possible without binding the connection first.');
            }

            $this->results = [];
            $con = $this->connection->getResource();

            switch ($this->options['scope']) {
                case static::SCOPE_BASE:
                    $func = 'ldap_read';
                    break;
                case static::SCOPE_ONE:
                    $func = 'ldap_list';
                    break;
                case static::SCOPE_SUB:
                    $func = 'ldap_search';
                    break;
                default:
                    throw new LdapException(sprintf('Could not search in scope "%s".', $this->options['scope']));
            }

            $itemsLeft = $maxItems = $this->options['maxItems'];
            $pageSize = $this->options['pageSize'];
            // Deal with the logic to handle maxItems properly. If we can satisfy it in
            // one request based on pageSize, we don't need to bother sending page control
            // to the server so that it can determine what we already know.
            if (0 !== $maxItems && $pageSize > $maxItems) {
                $pageSize = 0;
            } elseif (0 !== $maxItems) {
                $pageSize = min($maxItems, $pageSize);
            }
            $pageControl = $this->options['scope'] != static::SCOPE_BASE && $pageSize > 0;
            $cookie = '';
            do {
                if ($pageControl) {
                    ldap_control_paged_result($con, $pageSize, true, $cookie);
                }
                $sizeLimit = $itemsLeft;
                if ($pageSize > 0 && $sizeLimit >= $pageSize) {
                    $sizeLimit = 0;
                }
                $search = @$func(
                    $con,
                    $this->dn,
                    $this->query,
                    $this->options['filter'],
                    $this->options['attrsOnly'],
                    $sizeLimit,
                    $this->options['timeout'],
                    $this->options['deref']
                );

                if (false === $search) {
                    $ldapError = '';
                    if ($errno = ldap_errno($con)) {
                        $ldapError = sprintf(' LDAP error was [%d] %s', $errno, ldap_error($con));
                    }
                    if ($pageControl) {
                        $this->resetPagination();
                    }

                    throw new LdapException(sprintf('Could not complete search with dn "%s", query "%s" and filters "%s".%s', $this->dn, $this->query, implode(',', $this->options['filter']), $ldapError));
                }

                $this->results[] = $search;
                $itemsLeft -= min($itemsLeft, $pageSize);

                if (0 !== $maxItems && 0 === $itemsLeft) {
                    break;
                }
                if ($pageControl) {
                    ldap_control_paged_result_response($con, $search, $cookie);
                }
            } while (null !== $cookie && '' !== $cookie);

            if ($pageControl) {
                $this->resetPagination();
            }
        }

        return new Collection($this->connection, $this);
    }

    /**
     * Returns a LDAP search resource. If this query resulted in multiple searches, only the first
     * page will be returned.
     *
     * @return resource
     *
     * @internal
     */
    public function getResource(int $idx = 0)
    {
        if (null === $this->results || $idx >= \count($this->results)) {
            return null;
        }

        return $this->results[$idx];
    }

    /**
     * Returns all LDAP search resources.
     *
     * @return resource[]
     *
     * @internal
     */
    public function getResources(): array
    {
        return $this->results;
    }

    /**
     * Resets pagination on the current connection.
     */
    private function resetPagination()
    {
        $con = $this->connection->getResource();
        ldap_control_paged_result($con, 0);

        // This is a workaround for a bit of a bug in the above invocation
        // of ldap_control_paged_result. Instead of indicating to extldap that
        // we no longer wish to page queries on this link, this invocation sets
        // the LDAP_CONTROL_PAGEDRESULTS OID with a page size of 0. This isn't
        // well defined by RFC 2696 if there is no cookie present, so some servers
        // will interpret it differently and do the wrong thing. Forcefully remove
        // the OID for now until a fix can make its way through the versions of PHP
        // the we support.
        //
        // This is not supported in PHP < 7.2, so these versions will remain broken.
        $ctl = [];
        ldap_get_option($con, LDAP_OPT_SERVER_CONTROLS, $ctl);
        if (!empty($ctl)) {
            foreach ($ctl as $idx => $info) {
                if (static::PAGINATION_OID == $info['oid']) {
                    unset($ctl[$idx]);
                }
            }
            ldap_set_option($con, LDAP_OPT_SERVER_CONTROLS, $ctl);
        }
    }
}
