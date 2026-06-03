<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
interface AdapterInterface
{
    /**
     * Returns the current connection.
     */
    public function getConnection(): ConnectionInterface;

    /**
     * Creates a new Query.
     */
    public function createQuery(string $dn, string $query, array $options = []): QueryInterface;

    /**
     * Fetches the entry manager instance.
     */
    public function getEntryManager(): EntryManagerInterface;

    /**
     * Escape a string for use in an LDAP filter or DN.
     */
    public function escape(string $subject, string $ignore = '', int $flags = 0): string;
}
