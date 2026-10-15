CHANGELOG
=========

8.2
---

 * Add the component as experimental
 * Add an optional `$clock` argument to `InMemoryDataKeyStore`
 * Add `key_management.doctrine_dbal.types` to declare encrypted Doctrine DBAL types
 * Add `#[Encrypted]` to encrypt Doctrine ORM columns, registering the Doctrine DBAL types they need
 * Add the `key_management.store.read_self_contained` option, off by default
 * Add `StoredDataKey::wrappingContextFor()` and `RewrappableDataKeyStoreInterface::wrappingContextOf()`
 * Add the `key_management.store.bind_wrapping_context` option, and `--accept-unbound` to `key-management:rewrap-data-keys`
 * Add an optional `$bindWrappingContext` argument to `InMemoryDataKeyStore`
