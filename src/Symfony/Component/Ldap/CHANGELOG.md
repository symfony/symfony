CHANGELOG
=========

6.2
---

 * Deprecate `{username}` parameter use in favour of `{user_identifier}`

6.1
---

 * Return a 500 Internal Server Error if LDAP server in unavailable during user enumeration / authentication
 * Introduce `InvalidSearchCredentialsException` to differentiate between cases where user-provided credentials are invalid and cases where the configured search credentials are invalid

6.0
---

 * Removed `LdapUser::getUsername()` method, use `getUserIdentifier()` instead
 * Removed `LdapUserProvider::loadUserByUsername()` method, use `loadUserByIdentifier()` instead

5.3
---

 * The authenticator system is no longer experimental
 * Added caseSensitive option for attribute keys in the Entry class.

5.1.0
-----

 * Added `Security\LdapBadge`, `Security\LdapAuthenticator` and `Security\CheckLdapCredentialsListener` to integrate with the authenticator Security system

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
