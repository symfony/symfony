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

use LDAP\Connection as LDAPConnection;
use Symfony\Component\Ldap\Adapter\AbstractConnection;
use Symfony\Component\Ldap\Exception\AlreadyExistsException;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\ConnectionTimeoutException;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Connection extends AbstractConnection
{
    private const LDAP_INVALID_CREDENTIALS = 0x31;
    private const LDAP_TIMEOUT = 0x55;
    private const LDAP_ALREADY_EXISTS = 0x44;
    private const PRECONNECT_OPTIONS = [
        ConnectionOptions::DEBUG_LEVEL,
        ConnectionOptions::X_TLS_CACERTDIR,
        ConnectionOptions::X_TLS_CACERTFILE,
        ConnectionOptions::X_TLS_REQUIRE_CERT,
    ];

    private bool $bound = false;

    /** @var resource|LDAPConnection */
    private $connection;

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function isBound(): bool
    {
        return $this->bound;
    }

    /**
     * @param string $password WARNING: When the LDAP server allows unauthenticated binds, a blank $password will always be valid
     *
     * @return void
     */
    public function bind(string $dn = null, #[\SensitiveParameter] string $password = null)
    {
        if (!$this->connection) {
            $this->connect();
        }

        if (false === @ldap_bind($this->connection, $dn, $password)) {
            $error = ldap_error($this->connection);
            switch (ldap_errno($this->connection)) {
                case self::LDAP_INVALID_CREDENTIALS:
                    throw new InvalidCredentialsException($error);
                case self::LDAP_TIMEOUT:
                    throw new ConnectionTimeoutException($error);
                case self::LDAP_ALREADY_EXISTS:
                    throw new AlreadyExistsException($error);
            }
            throw new ConnectionException($error);
        }

        $this->bound = true;
    }

    /**
     * @return resource|LDAPConnection
     *
     * @internal
     */
    public function getResource()
    {
        return $this->connection;
    }

    /**
     * @return void
     */
    public function setOption(string $name, array|string|int|bool $value)
    {
        if (!@ldap_set_option($this->connection, ConnectionOptions::getOption($name), $value)) {
            throw new LdapException(sprintf('Could not set value "%s" for option "%s".', $value, $name));
        }
    }

    /**
     * @return array|string|int|null
     */
    public function getOption(string $name)
    {
        if (!@ldap_get_option($this->connection, ConnectionOptions::getOption($name), $ret)) {
            throw new LdapException(sprintf('Could not retrieve value for option "%s".', $name));
        }

        return $ret;
    }

    /**
     * @return void
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('debug', false);
        $resolver->setAllowedTypes('debug', 'bool');
        $resolver->setDefault('referrals', false);
        $resolver->setAllowedTypes('referrals', 'bool');
        $resolver->setDefault('options', function (OptionsResolver $options, Options $parent) {
            $options->setDefined(array_map('strtolower', array_keys((new \ReflectionClass(ConnectionOptions::class))->getConstants())));

            if (true === $parent['debug']) {
                $options->setDefault('debug_level', 7);
            }

            if (!isset($parent['network_timeout'])) {
                $options->setDefault('network_timeout', \ini_get('default_socket_timeout'));
            }

            $options->setDefaults([
                'protocol_version' => $parent['version'],
                'referrals' => $parent['referrals'],
            ]);
        });
    }

    private function connect(): void
    {
        if ($this->connection) {
            return;
        }

        foreach ($this->config['options'] as $name => $value) {
            if (\in_array(ConnectionOptions::getOption($name), self::PRECONNECT_OPTIONS, true)) {
                $this->setOption($name, $value);
            }
        }

        if (false === $connection = ldap_connect($this->config['connection_string'])) {
            throw new LdapException('Invalid connection string: '.$this->config['connection_string']);
        } else {
            $this->connection = $connection;
        }

        foreach ($this->config['options'] as $name => $value) {
            if (!\in_array(ConnectionOptions::getOption($name), self::PRECONNECT_OPTIONS, true)) {
                $this->setOption($name, $value);
            }
        }

        if ('tls' === $this->config['encryption'] && false === @ldap_start_tls($this->connection)) {
            throw new LdapException('Could not initiate TLS connection: '.ldap_error($this->connection));
        }
    }

    private function disconnect(): void
    {
        if ($this->connection) {
            ldap_unbind($this->connection);
        }

        $this->connection = null;
        $this->bound = false;
    }
}
