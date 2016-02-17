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

use Symfony\Component\Ldap\Adapter\AbstractConnection;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Connection extends AbstractConnection
{
    /** @var bool */
    private $bound = false;

    /** @var resource */
    private $connection;

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * {@inheritdoc}
     */
    public function isBound()
    {
        return $this->bound;
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

        $this->bound = true;
    }

    /**
     * Returns a link resource.
     *
     * @return resource
     *
     * @internal
     */
    public function getResource()
    {
        return $this->connection;
    }

    private function connect()
    {
        if ($this->connection) {
            return;
        }

        $host = $this->config['host'];

        ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, $this->config['version']);
        ldap_set_option($this->connection, LDAP_OPT_REFERRALS, $this->config['optReferrals']);

        $this->connection = ldap_connect($host, $this->config['port']);

        if (false === $this->connection) {
            throw new LdapException(sprintf('Could not connect to Ldap server: %s', ldap_error($this->connection)));
        }

        if ($this->config['useStartTls'] && false === ldap_start_tls($this->connection)) {
            throw new LdapException(sprintf('Could not initiate TLS connection: %s', ldap_error($this->connection)));
        }
    }

    private function disconnect()
    {
        if ($this->connection && is_resource($this->connection)) {
            ldap_close($this->connection);
        }

        $this->connection = null;
        $this->bound = false;
    }
}
