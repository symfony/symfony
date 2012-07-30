<?php

namespace Symfony\Component\Security\Ldap;

use Symfony\Component\Security\Ldap\Exception\ConnectionException;

interface LdapInterface
{
    /**
     * return a connection
     *
     * @return ressource A connection
     */
    public function getConnection();

    /**
     * return a connection binded to the ldap
     *
     * @return ressource A connection
     *
     * @throws ConnectionException If username / password are not bindable
     */
    public function bind();

    /**
     * getHost
     *
     * @return string The host
     */
    public function getHost();

    /**
     * setHost
     *
     * @param string $host The host
     */
    public function setHost($host);

    /**
     * getPort
     *
     * @return integer The port
     */
    public function getPort();

    /**
     * setPort
     *
     * @param integer $port The port
     */
    public function setPort($port);

    /**
     * getDn
     *
     * @return string The dn
     */
    public function getDn();

    /**
     * setDn
     *
     * @param string $dn The dn
     */
    public function setDn($dn);

    /**
     * getUsernameSuffix
     *
     * @return string The username suffix
     */
    public function getUsernameSuffix();

    /**
     * setUsernameSuffix
     *
     * @param string $usernameSuffix The username Suffix
     */
    public function setUsernameSuffix($usernameSuffix);

    /**
     * getVersion
     *
     * @return integer The version
     */
    public function getVersion();

    /**
     * setVersion
     *
     * @param integer $version The version
     */
    public function setVersion($version);

    /**
     * getUseSsl
     *
     * @return boolean The use SSL
     */
    public function getUseSsl();

    /**
     * setUseSsl
     *
     * @param boolean $useSsl The use SSL
     */
    public function setUseSsl($useSsl);

    /**
     * getUseStartTls
     *
     * @return boolean The use start ssl
     */
    public function getUseStartTls();

    /**
     * setUseStartTls
     *
     * @param boolean $useStartTls The use start ssl
     */
    public function setUseStartTls($useStartTls);

    /**
     * getOptReferrals
     *
     * @return boolean The opt referrals
     */
    public function getOptReferrals();

    /**
     * setOptReferrals
     *
     * @param boolean $optReferrals the opt referrals
     */
    public function setOptReferrals($optReferrals);

    /**
     * getUsername
     *
     * @return string The username
     */
    public function getUsername();

    /**
     * setUsername
     *
     * @param string $username The username
     */
    public function setUsername($username);

    /**
     * getPassword
     *
     * @return string The password
     */
    public function getPassword();

    /**
     * setPassword
     *
     * @param string $password The password
     */
    public function setPassword($password);

    /**
     * getUsernameWithSuffix return the concatenation
     * between the username and the usernameSufix
     *
     * @param  string|null $username A username
     * @return string      The username with the suffix
     */
    public function getUsernameWithSuffix($username = null);

    /*
     * find a username into ldap connection
     *
     * @todo : not needed anymore. But could be usefull to retrieve roles
     *
     * @param  string     $username
     * @param  string     $query
     * @param  string     $filter
     * @return array|null
     */
    public function findByUsername($username, $query, $filter);
}
