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
use Symfony\Component\Ldap\Exception\NotBoundException;
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
    private ?LDAPConnection $connection = null;

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup(): void
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
     */
    public function bind(?string $dn = null, #[\SensitiveParameter] ?string $password = null): void
    {
        if (!$this->connection) {
            $this->connect();
        }

        if (false === @ldap_bind($this->connection, $dn, $password)) {
            $error = ldap_error($this->connection);
            ldap_get_option($this->connection, \LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnostic);

            throw match (ldap_errno($this->connection)) {
                self::LDAP_INVALID_CREDENTIALS => new InvalidCredentialsException($error),
                self::LDAP_TIMEOUT => new ConnectionTimeoutException($error),
                self::LDAP_ALREADY_EXISTS => new AlreadyExistsException($error),
                default => new ConnectionException($error.' '.$diagnostic),
            };
        }

        $this->bound = true;
    }

    /**
     * @param string $password WARNING: When the LDAP server allows unauthenticated binds, a blank $password will always be valid
     */
    public function saslBind(?string $dn = null, #[\SensitiveParameter] ?string $password = null, ?string $mech = null, ?string $realm = null, ?string $authcId = null, ?string $authzId = null, ?string $props = null): void
    {
        if (!\function_exists('ldap_sasl_bind')) {
            throw new LdapException('The LDAP extension is missing SASL support.');
        }

        if (!$this->connection) {
            $this->connect();
        }

        if (false === @ldap_sasl_bind($this->connection, $dn, $password, $mech, $realm, $authcId, $authzId, $props)) {
            $error = ldap_error($this->connection);
            ldap_get_option($this->connection, \LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnostic);

            throw match (ldap_errno($this->connection)) {
                self::LDAP_INVALID_CREDENTIALS => new InvalidCredentialsException($error),
                self::LDAP_TIMEOUT => new ConnectionTimeoutException($error),
                self::LDAP_ALREADY_EXISTS => new AlreadyExistsException($error),
                default => new ConnectionException($error.' '.$diagnostic),
            };
        }

        $this->bound = true;
    }

    /**
     * ldap_exop_whoami accessor, returns authenticated DN.
     */
    public function whoami(): string
    {
        if (!$this->connection) {
            throw new NotBoundException(\sprintf('Cannot execute "%s()" before calling "%s::saslBind()".', __METHOD__, __CLASS__));
        }

        if (false === $authzId = ldap_exop_whoami($this->connection)) {
            throw new LdapException(ldap_error($this->connection));
        }

        $parts = explode(':', $authzId, 2);
        if ('dn' !== $parts[0]) {
            /*
             * We currently do not handle u:login authzId, which
             * would require a configuration-dependent LDAP search
             * to be turned into a DN
             */
            throw new LdapException(\sprintf('Unsupported authzId "%s".', $authzId));
        }

        return $parts[1];
    }

    /**
     * @internal
     */
    public function getResource(): ?LDAPConnection
    {
        return $this->connection;
    }

    public function setOption(string $name, array|string|int|bool $value): void
    {
        if (!@ldap_set_option($this->connection, ConnectionOptions::getOption($name), $value)) {
            throw new LdapException(\sprintf('Could not set value "%s" for option "%s".', $value, $name));
        }
    }

    public function getOption(string $name): array|string|int|null
    {
        if (!@ldap_get_option($this->connection, ConnectionOptions::getOption($name), $ret)) {
            throw new LdapException(\sprintf('Could not retrieve value for option "%s".', $name));
        }

        return $ret;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('debug', false);
        $resolver->setAllowedTypes('debug', 'bool');
        $resolver->setDefault('referrals', false);
        $resolver->setAllowedTypes('referrals', 'bool');
        $resolver->setOptions('options', function (OptionsResolver $options, Options $parent) {
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
        }

        $this->connection = $connection;

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
