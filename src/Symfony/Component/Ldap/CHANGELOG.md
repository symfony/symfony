CHANGELOG
=========

5.0.0
-----

 * Added method `move() to `EntryManagerInterface`
 * Added pagination support to the ExtLdap adapter with the pageSize query option

4.4.0
-----

 * Added the "extra_fields" option, an array of custom fields to pull from the LDAP server

4.3.0
-----

 * Added `EntryManager::move`, not implementing it is deprecated
 * Added pagination support to the ExtLdap adapter with the pageSize query option

4.2.0
-----

 * Added `EntryManager::applyOperations`
 * Added timeout option to `ConnectionOptions`

4.1.0
-----

 * Added support for adding values to multi-valued attributes
 * Added support for removing values from multi-valued attributes

4.0.0
-----

 * Removed the `LdapClient` class and the `LdapClientInterface`
 * Removed the `RenameEntryInterface` interface and merged with `EntryManagerInterface`

3.3.0
-----

 * The `RenameEntryInterface` inferface is deprecated, and will be merged with `EntryManagerInterface` in 4.0.

3.1.0
-----

 * The `LdapClient` class is deprecated. Use the `Ldap` class instead.
