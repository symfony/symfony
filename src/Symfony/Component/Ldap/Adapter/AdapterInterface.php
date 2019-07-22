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
     *
     * @return ConnectionInterface
     */
    public function getConnection();

    /**
     * Creates a new Query.
     *
     * @return QueryInterface
     */
    public function createQuery(string $dn, string $query, array $options = []);

    /**
     * Fetches the entry manager instance.
     *
     * @return EntryManagerInterface
     */
    public function getEntryManager();

    /**
     * Escape a string for use in an LDAP filter or DN.
     *
     * @return string
     */
    public function escape(string $subject, string $ignore = '', int $flags = 0);
}
