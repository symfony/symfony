<?php

namespace Symfony\Component\Security\Ldap;

use Symfony\Component\Security\Ldap\Exception\ConnectionException;
use Symfony\Component\Security\Ldap\Exception\LdapException;

/*
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Francis Besset <francis.besset@gmail.com>
 */
class Ldap implements LdapInterface
{
    private $host;
    private $port;
    private $dn;
    private $usernameSuffix;
    private $version;
    private $useSsl;
    private $useStartTls;
    private $optReferrals;
    private $username;
    private $password;

    private $connection;

    /**
     * contructor
     *
     * @param string  $host
     * @param integer $port
     * @param string  $dn
     * @param string  $usernameSuffix
     * @param integer $version
     * @param boolean $useSsl
     * @param boolean $useStartTls
     * @param boolean $optReferrals
     */
    public function __construct($host = null, $port = 389, $dn = null, $usernameSuffix = null, $version = 3, $useSsl = false, $useStartTls = false, $optReferrals = false)
    {
        if (!extension_loaded('ldap')) {
            throw new LdapException('Ldap module is needed.');
        }

        $this->host           = $host;
        $this->port           = $port;
        $this->dn             = $dn;
        $this->usernameSuffix = $usernameSuffix;
        $this->version        = $version;
        $this->useSsl         = (boolean) $useSsl;
        $this->useStartTls    = (boolean) $useStartTls;
        $this->optReferrals   = (boolean) $optReferrals;

        $this->connection = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection()
    {
        if (!$this->connection) {
            $this->connect();
        }

        return $this->connection;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function bind()
    {
        if (!$this->connection) {
            $this->connect();
        }

        if (false === @ldap_bind($this->connection, $this->getUsernameWithSuffix(), $this->getPassword())) {
            throw new ConnectionException(sprintf('Username / password invalid to connect on Ldap server %s:%s', $this->host, $this->port));
        }

        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * {@inheritdoc}
     */
    public function setDn($dn)
    {
        $this->dn = $dn;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsernameSuffix()
    {
        return $this->usernameSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function setUsernameSuffix($usernameSuffix)
    {
        $this->usernameSuffix = $usernameSuffix;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * {@inheritdoc}
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUseSsl()
    {
        return $this->useSsl;
    }

    /**
     * {@inheritdoc}
     */
    public function setUseSsl($useSsl)
    {
        $this->useSsl = (boolean) $useSsl;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUseStartTls()
    {
        return $this->useStartTls;
    }

    /**
     * {@inheritdoc}
     */
    public function setUseStartTls($useStartTls)
    {
        $this->useStartTls = (boolean) $useStartTls;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptReferrals()
    {
        return $this->optReferrals;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptReferrals($optReferrals)
    {
        $this->optReferrals = (boolean) $optReferrals;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsernameWithSuffix($username = null)
    {
        if (null === $username) {
            $username = $this->username;
        }

        return $username.$this->usernameSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function findByUsername($username, $query, $filter = '*')
    {
        if (!$this->connection) {
            $this->connect();
        }

        if (!is_array($filter)) {
            $filter = array($filter);
        }

        $username = $this->escapeValue($this->getUsernameWithSuffix());

        $query  = sprintf($query, $username);
        $search = ldap_search($this->connection, $this->getDn(), $query, $filter);
        $infos  = ldap_get_entries($this->connection, $search);

        if (0 === $infos['count']) {
            return null;
        }

        return $infos[0];
    }

    private function connect()
    {
        if (!$this->connection) {
            $host = $this->getHost();
            if ($this->getUseSsl()) {
                $host = 'ldaps://' . $host;
            }
            ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, $this->getVersion());
            ldap_set_option($this->connection, LDAP_OPT_REFERRALS, $this->getOptReferrals());

            if ($this->getUseStartTls()) {
                ldap_start_tls($this->connection);
            }

            $this->connection = ldap_connect($host, $this->getPort());
        }

        return $this;
    }

    private function disconnect()
    {
        if ($this->connection && is_resource($this->connection)) {
            ldap_unbind($this->connection);
        }

        $this->connection = null;

        return $this;
    }

    /**
    * Escapes the given VALUES according to RFC 2254 so that they can be safely used in LDAP filters.
    *
    * Any control characters with an ASCII code < 32 as well as the characters with special meaning in
    * LDAP filters "*", "(", ")", and "\" (the backslash) are converted into the representation of a
    * backslash followed by two hex digits representing the hexadecimal value of the character.

    * @see Net_LDAP2_Util::escape_filter_value() from Benedikt Hallinger <beni@php.net>
    * @link http://pear.php.net/package/Net_LDAP2
    * @author Benedikt Hallinger <beni@php.net>
    *
    * @param string|array $values Array of values to escape
    * @return array Array $values, but escaped
    */
    private function escapeValue($value)
    {
        // Escaping of filter meta characters
        $value = str_replace(array('\\', '*', '(', ')'), array('\5c', '\2a', '\28', '\29'), $value);
        // ASCII < 32 escaping
        for ($i = 0; $i < strlen($value); $i++) {
            $char = substr($value, $i, 1);
            if (ord($char) < 32) {
                $hex = dechex(ord($char));
                if (strlen($hex) == 1)
                    $hex = '0' . $hex;
                $value = str_replace($char, '\\' . $hex, $value);
            }
        }
        if (null === $value) {
            $value = '\0'; // apply escaped "null" if string is empty
        }

        return $value;
    }
}
