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

use Symfony\Component\Ldap\Adapter\AdapterInterface;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Adapter implements AdapterInterface
{
    private $config;
    private $connection;
    private $entryManager;

    public function __construct(array $config = [])
    {
        if (!\extension_loaded('ldap')) {
            throw new LdapException('The LDAP PHP extension is not enabled.');
        }

        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection()
    {
        if (null === $this->connection) {
            $this->connection = new Connection($this->config);
        }

        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntryManager()
    {
        if (null === $this->entryManager) {
            $this->entryManager = new EntryManager($this->getConnection());
        }

        return $this->entryManager;
    }

    /**
     * {@inheritdoc}
     */
    public function createQuery(string $dn, string $query, array $options = [])
    {
        return new Query($this->getConnection(), $dn, $query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function escape(string $subject, string $ignore = '', int $flags = 0)
    {
        $value = ldap_escape($subject, $ignore, $flags);

        // Per RFC 4514, leading/trailing spaces should be encoded in DNs, as well as carriage returns.
        if ((int) $flags & \LDAP_ESCAPE_DN) {
            if (!empty($value) && ' ' === $value[0]) {
                $value = '\\20'.substr($value, 1);
            }
            if (!empty($value) && ' ' === $value[\strlen($value) - 1]) {
                $value = substr($value, 0, -1).'\\20';
            }
            $value = str_replace("\r", '\0d', $value);
        }

        return $value;
    }
}
