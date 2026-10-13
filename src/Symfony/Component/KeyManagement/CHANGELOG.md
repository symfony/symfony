CHANGELOG
=========

8.2
---

 * Add the component as experimental
 * Add an optional `$clock` argument to `InMemoryDataKeyStore`
 * Add `key_management.doctrine_dbal.types` to declare encrypted Doctrine DBAL types when the kernel boots
 * Add `#[Encrypted]` to encrypt Doctrine ORM columns, registering the Doctrine DBAL types they need when the kernel boots
 * Add the `key_management.store.read_self_contained` option, off by default
