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

use Symfony\Component\Ldap\Entry;

/**
 * Entry manager interface.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 */
interface EntryManagerInterface
{
    /**
     * Adds a new entry in the Ldap server.
     *
     * @param Entry $entry
     */
    public function add(Entry $entry);

    /**
     * Updates an entry from the Ldap server.
     *
     * @param Entry $entry
     */
    public function update(Entry $entry);

    /**
     * Removes an entry from the Ldap server.
     *
     * @param Entry $entry
     */
    public function remove(Entry $entry);
}
