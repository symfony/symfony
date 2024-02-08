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
use Symfony\Component\Ldap\Adapter\ConnectionInterface;
use Symfony\Component\Ldap\Adapter\EntryManagerInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Adapter implements AdapterInterface
{
    private array $config;
    private ConnectionInterface $connection;
    private EntryManagerInterface $entryManager;

    public function __construct(array $config = [])
    {
        if (!\extension_loaded('ldap')) {
            throw new LdapException('The LDAP PHP extension is not enabled.');
        }

        $this->config = $config;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection ??= new Connection($this->config);
    }

    public function getEntryManager(): EntryManagerInterface
    {
        return $this->entryManager ??= new EntryManager($this->getConnection());
    }

    public function createQuery(string $dn, string $query, array $options = []): QueryInterface
    {
        return new Query($this->getConnection(), $dn, $query, $options);
    }

    public function escape(string $subject, string $ignore = '', int $flags = 0): string
    {
        $value = ldap_escape($subject, $ignore, $flags);

        // Per RFC 4514, leading/trailing spaces should be encoded in DNs, as well as carriage returns.
        if ($flags & \LDAP_ESCAPE_DN) {
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
