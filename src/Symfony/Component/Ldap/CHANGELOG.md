CHANGELOG
=========

4.2.0
-----

 * added `EntryManager::applyOperations`

4.1.0
-----

 * Added support for adding values to multi-valued attributes
 * Added support for removing values from multi-valued attributes

4.0.0
-----

 * removed the `LdapClient` class and the `LdapClientInterface`
 * removed the `RenameEntryInterface` interface and merged with `EntryManagerInterface`

3.3.0
-----

* The `RenameEntryInterface` inferface is deprecated, and will be merged with `EntryManagerInterface` in 4.0.

3.1.0
-----

 * The `LdapClient` class is deprecated. Use the `Ldap` class instead.
