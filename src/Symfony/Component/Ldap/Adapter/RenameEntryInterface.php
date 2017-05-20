<?php

namespace Symfony\Component\Ldap\Adapter;

use Symfony\Component\Ldap\Entry;

/**
 * @deprecated This interface will be deprecated in 4.0, and merged with `EntryManagerInterface`
 *
 * @author Kevin Schuurmans <kevin.schuurmans@freshheads.com>
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
