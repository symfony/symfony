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

use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @internal
 */
class LdapClient implements LdapClientInterface
{
    private $host;
    private $port;
    private $version;
    private $useSsl;
    private $useStartTls;
    private $optReferrals;
    private $connection;

    /**
     * Constructor.
     *
     * @param string $host
     * @param int    $port
     * @param int    $version
     * @param bool   $useSsl
     * @param bool   $useStartTls
     * @param bool   $optReferrals
     */
    public function __construct($host = null, $port = 389, $version = 3, $useSsl = false, $useStartTls = false, $optReferrals = false)
    {
        if (!extension_loaded('ldap')) {
            throw new LdapException('The ldap module is needed.');
        }

        $this->host = $host;
        $this->port = $port;
        $this->version = $version;
        $this->useSsl = (bool) $useSsl;
        $this->useStartTls = (bool) $useStartTls;
        $this->optReferrals = (bool) $optReferrals;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * {@inheritdoc}
     */
    public function bind($dn = null, $password = null)
    {
        if (!$this->connection) {
            $this->connect();
        }

        if (false === @ldap_bind($this->connection, $dn, $password)) {
            throw new ConnectionException(ldap_error($this->connection));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function find($dn, $query, $filter = '*')
    {
        if (!is_array($filter)) {
            $filter = array($filter);
        }

        $search = ldap_search($this->connection, $dn, $query, $filter);
        $infos = ldap_get_entries($this->connection, $search);

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

    private function connect()
    {
        if (!$this->connection) {
            $host = $this->host;

            if ($this->useSsl) {
                $host = 'ldaps://'.$host;
            }

            $this->connection = ldap_connect($host, $this->port);

            ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, $this->version);
            ldap_set_option($this->connection, LDAP_OPT_REFERRALS, $this->optReferrals);

            if ($this->useStartTls) {
                ldap_start_tls($this->connection);
            }
        }
    }

    private function disconnect()
    {
        if ($this->connection && is_resource($this->connection)) {
            ldap_unbind($this->connection);
        }

        $this->connection = null;
    }
}
