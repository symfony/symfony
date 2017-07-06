<?php

namespace Symfony\Component\Ldap\Adapter;

use Symfony\Component\Ldap\Entry;

/**
 * @author Kevin Schuurmans <kevin.schuurmans@freshheads.com>
 *
 * @deprecated since version 3.3, will be merged with {@link EntryManagerInterface} in 4.0.
 */
interface RenameEntryInterface
{
    /**
     * Renames an entry on the Ldap server.
     *
     * @param Entry  $entry
     * @param string $newRdn
     * @param bool   $removeOldRdn
     */
    public function rename(Entry $entry, $newRdn, $removeOldRdn = true);
}
