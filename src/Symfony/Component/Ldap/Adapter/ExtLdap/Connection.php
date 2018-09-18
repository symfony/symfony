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
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

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

    public function setOption($name, $value)
    {
        if (!@ldap_set_option($this->connection, ConnectionOptions::getOption($name), $value)) {
            throw new LdapException(sprintf('Could not set value "%s" for option "%s".', $value, $name));
        }
    }

    public function getOption($name)
    {
        if (!@ldap_get_option($this->connection, ConnectionOptions::getOption($name), $ret)) {
            throw new LdapException(sprintf('Could not retrieve value for option "%s".', $name));
        }

        return $ret;
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('debug', false);
        $resolver->setAllowedTypes('debug', 'bool');
        $resolver->setDefault('referrals', false);
        $resolver->setAllowedTypes('referrals', 'bool');

        $resolver->setNormalizer('options', function (Options $options, $value) {
            if (true === $options['debug']) {
                $value['debug_level'] = 7;
            }

            if (!isset($value['protocol_version'])) {
                $value['protocol_version'] = $options['version'];
            }

            if (!isset($value['referrals'])) {
                $value['referrals'] = $options['referrals'];
            }

            return $value;
        });

        $resolver->setAllowedValues('options', function (array $values) {
            foreach ($values as $name => $value) {
                if (!ConnectionOptions::isOption($name)) {
                    return false;
                }
            }

            return true;
        });
    }

    private function connect()
    {
        if ($this->connection) {
            return;
        }

        $this->connection = ldap_connect($this->config['connection_string']);

        foreach ($this->config['options'] as $name => $value) {
            $this->setOption($name, $value);
        }

        if (false === $this->connection) {
            throw new LdapException(sprintf('Could not connect to Ldap server: %s.', ldap_error($this->connection)));
        }

        if ('tls' === $this->config['encryption'] && false === @ldap_start_tls($this->connection)) {
            throw new LdapException(sprintf('Could not initiate TLS connection: %s.', ldap_error($this->connection)));
        }
    }

    private function disconnect()
    {
        if ($this->connection && \is_resource($this->connection)) {
            ldap_unbind($this->connection);
        }

        $this->connection = null;
        $this->bound = false;
    }
}
