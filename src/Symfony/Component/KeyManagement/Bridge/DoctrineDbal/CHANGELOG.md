CHANGELOG
=========

8.2
---

 * Add the bridge as experimental
 * Add an optional `$clock` argument to `DataKeyStore`, used to date the data keys and to rotate them by age
 * Add the `$bindWrappingContext` argument to `DataKeyStore::__construct()`, off by default
 * Add the optional `$typeName` argument to `EncryptedType` and authenticate the configured key and parent type name
