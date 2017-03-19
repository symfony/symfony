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
     * @param string $dn
     * @param string $query
     * @param array  $options
     *
     * @return QueryInterface
     */
    public function createQuery($dn, $query, array $options = array());

    /**
     * Fetches the entry manager instance.
     *
     * @return EntryManagerInterface
     */
    public function getEntryManager();

    /**
     * Escape a string for use in an LDAP filter or DN.
     *
     * @param string $subject
     * @param string $ignore
     * @param int    $flags
     *
     * @return string
     */
    public function escape($subject, $ignore = '', $flags = 0);
}
