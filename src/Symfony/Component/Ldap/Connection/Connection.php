<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Connection;

use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Connection implements ConnectionInterface
{
    private $bound = false;
    private $connection;
    private $config;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $config = array())
    {
        if (!extension_loaded('ldap')) {
            throw new LdapException('The ldap module is needed.');
        }

        $resolver = new OptionsResolver();
        $resolver->setDefaults(array(
            'host' => null,
            'port' => 389,
            'version' => 3,
            'useSsl' => false,
            'useStartTls' => false,
            'optReferrals' => false,
        ));
        $resolver->setNormalizer('host', function (Options $options, $value) {
            if ($value && $options['useSsl']) {
                return 'ldaps://'.$value;
            }

            return $value;
        });

        $this->config = $resolver->resolve($config);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

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

    public function isConnected()
    {
        return null !== $this->connection;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function connect()
    {
        if (!$this->connection) {
            $host = $this->config['host'];

            ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, $this->config['version']);
            ldap_set_option($this->connection, LDAP_OPT_REFERRALS, $this->config['optReferrals']);

            $this->connection = ldap_connect($host, $this->config['port']);

            if ($this->config['useStartTls']) {
                ldap_start_tls($this->connection);
            }
        }
    }

    public function disconnect()
    {
        if ($this->connection && is_resource($this->connection)) {
            ldap_unbind($this->connection);
        }

        $this->connection = null;
    }
}
