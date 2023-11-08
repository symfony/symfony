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

use LDAP\Result;
use Symfony\Component\Ldap\Adapter\AbstractQuery;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 */
class Query extends AbstractQuery
{
    public const PAGINATION_OID = \LDAP_CONTROL_PAGEDRESULTS;

    /** @var Result[] */
    private array $results;

    private array $serverctrls = [];

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    /**
     * @return void
     */
    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $con = $this->connection->getResource();

        if (!isset($this->results)) {
            return;
        }

        foreach ($this->results as $result) {
            if (false === $result || null === $result) {
                continue;
            }
            if (!ldap_free_result($result)) {
                throw new LdapException('Could not free results: '.ldap_error($con));
            }
        }
    }

    public function execute(): CollectionInterface
    {
        if (!isset($this->results)) {
            // If the connection is not bound, throw an exception. Users should use an explicit bind call first.
            if (!$this->connection->isBound()) {
                throw new NotBoundException('Query execution is not possible without binding the connection first.');
            }

            $this->results = [];
            $con = $this->connection->getResource();

            $func = match ($this->options['scope']) {
                static::SCOPE_BASE => 'ldap_read',
                static::SCOPE_ONE => 'ldap_list',
                static::SCOPE_SUB => 'ldap_search',
                default => throw new LdapException(sprintf('Could not search in scope "%s".', $this->options['scope'])),
            };

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
                    $this->controlPagedResult($pageSize, true, $cookie);
                }
                $sizeLimit = $itemsLeft;
                if ($pageSize > 0 && $sizeLimit >= $pageSize) {
                    $sizeLimit = 0;
                }
                $search = @$func($con, $this->dn, $this->query, $this->options['filter'], $this->options['attrsOnly'], $sizeLimit, $this->options['timeout'], $this->options['deref'], $this->serverctrls);

                if (false === $search) {
                    $ldapError = '';
                    if ($errno = ldap_errno($con)) {
                        $ldapError = sprintf(' LDAP error was [%d] %s', $errno, ldap_error($con));
                    }
                    if ($pageControl) {
                        $this->resetPagination();
                    }

                    throw new LdapException(sprintf('Could not complete search with dn "%s", query "%s" and filters "%s".%s.', $this->dn, $this->query, implode(',', $this->options['filter']), $ldapError), $errno);
                }

                $this->results[] = $search;
                $itemsLeft -= min($itemsLeft, $pageSize);

                if (0 !== $maxItems && 0 === $itemsLeft) {
                    break;
                }
                if ($pageControl) {
                    ldap_parse_result($con, $search, $errcode, $matcheddn, $errmsg, $referrals, $controls);

                    $cookie = $controls[\LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
                }
            } while (null !== $cookie && '' !== $cookie);

            if ($pageControl) {
                $this->resetPagination();
            }
        }

        return new Collection($this->connection, $this);
    }

    /**
     * Returns an LDAP search resource. If this query resulted in multiple searches, only the first
     * page will be returned.
     *
     * @internal
     */
    public function getResource(int $idx = 0): ?Result
    {
        return $this->results[$idx] ?? null;
    }

    /**
     * Returns all LDAP search resources.
     *
     * @return Result[]
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
    private function resetPagination(): void
    {
        $con = $this->connection->getResource();
        $this->controlPagedResult(0, false, '');
        $this->serverctrls = [];

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
        ldap_get_option($con, \LDAP_OPT_SERVER_CONTROLS, $ctl);
        if (!empty($ctl)) {
            foreach ($ctl as $idx => $info) {
                if (static::PAGINATION_OID == $info['oid']) {
                    unset($ctl[$idx]);
                }
            }
            ldap_set_option($con, \LDAP_OPT_SERVER_CONTROLS, $ctl);
        }
    }

    /**
     * Sets LDAP pagination controls.
     */
    private function controlPagedResult(int $pageSize, bool $critical, string $cookie): bool
    {
        $this->serverctrls = [
            [
                'oid' => \LDAP_CONTROL_PAGEDRESULTS,
                'isCritical' => $critical,
                'value' => [
                    'size' => $pageSize,
                    'cookie' => $cookie,
                ],
            ],
        ];

        return true;
    }
}
