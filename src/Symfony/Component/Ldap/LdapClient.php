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
    private $charmaps;

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
        if (function_exists('ldap_escape')) {
            return ldap_escape($subject, $ignore, $flags);
        }

        return $this->doEscape($subject, $ignore, $flags);
    }

    private function connect()
    {
        if (!$this->connection) {
            $host = $this->host;

            if ($this->useSsl) {
                $host = 'ldaps://'.$host;
            }

            ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, $this->version);
            ldap_set_option($this->connection, LDAP_OPT_REFERRALS, $this->optReferrals);

            $this->connection = ldap_connect($host, $this->port);

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

    /**
     * Stub implementation of the {@link ldap_escape()} function of the ldap
     * extension.
     *
     * Escape strings for safe use in LDAP filters and DNs.
     *
     * @author Chris Wright <ldapi@daverandom.com>
     *
     * @param string $subject
     * @param string $ignore
     * @param int    $flags
     *
     * @return string
     *
     * @see http://stackoverflow.com/a/8561604
     */
    private function doEscape($subject, $ignore = '', $flags = 0)
    {
        $charMaps = $this->getCharmaps();

        // Create the base char map to escape
        $flags = (int) $flags;
        $charMap = array();

        if ($flags & self::LDAP_ESCAPE_FILTER) {
            $charMap += $charMaps[self::LDAP_ESCAPE_FILTER];
        }

        if ($flags & self::LDAP_ESCAPE_DN) {
            $charMap += $charMaps[self::LDAP_ESCAPE_DN];
        }

        if (!$charMap) {
            $charMap = $charMaps[0];
        }

        // Remove any chars to ignore from the list
        $ignore = (string) $ignore;

        for ($i = 0, $l = strlen($ignore); $i < $l; ++$i) {
            unset($charMap[$ignore[$i]]);
        }

        // Do the main replacement
        $result = strtr($subject, $charMap);

        // Encode leading/trailing spaces if LDAP_ESCAPE_DN is passed
        if ($flags & self::LDAP_ESCAPE_DN) {
            if ($result[0] === ' ') {
                $result = '\\20'.substr($result, 1);
            }

            if ($result[strlen($result) - 1] === ' ') {
                $result = substr($result, 0, -1).'\\20';
            }
        }

        return $result;
    }

    private function getCharmaps()
    {
        if (null !== $this->charmaps) {
            return $this->charmaps;
        }

        $charMaps = array(
            self::LDAP_ESCAPE_FILTER => array('\\', '*', '(', ')', "\x00"),
            self::LDAP_ESCAPE_DN => array('\\', ',', '=', '+', '<', '>', ';', '"', '#'),
        );

        $charMaps[0] = array();

        for ($i = 0; $i < 256; ++$i) {
            $charMaps[0][chr($i)] = sprintf('\\%02x', $i);
        }

        for ($i = 0, $l = count($charMaps[self::LDAP_ESCAPE_FILTER]); $i < $l; ++$i) {
            $chr = $charMaps[self::LDAP_ESCAPE_FILTER][$i];
            unset($charMaps[self::LDAP_ESCAPE_FILTER][$i]);
            $charMaps[self::LDAP_ESCAPE_FILTER][$chr] = $charMaps[0][$chr];
        }

        for ($i = 0, $l = count($charMaps[self::LDAP_ESCAPE_DN]); $i < $l; ++$i) {
            $chr = $charMaps[self::LDAP_ESCAPE_DN][$i];
            unset($charMaps[self::LDAP_ESCAPE_DN][$i]);
            $charMaps[self::LDAP_ESCAPE_DN][$chr] = $charMaps[0][$chr];
        }

        $this->charmaps = $charMaps;

        return $this->charmaps;
    }
}
