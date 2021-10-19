CHANGELOG
=========

5.4
---

* Add `DoctrineDbalStore` identical to `PdoStore` for `Doctrine\DBAL\Connection` or DBAL url
* Deprecate usage of `PdoStore` with `Doctrine\DBAL\Connection` or DBAL url
* Add `DoctrineDbalPostgreSqlStore` identical to `PdoPostgreSqlStore` for `Doctrine\DBAL\Connection` or DBAL url
* Deprecate usage of `PdoPostgreSqlStore` with `Doctrine\DBAL\Connection` or DBAL url

5.2.0
-----

 * `MongoDbStore` does not implement `BlockingStoreInterface` anymore, typehint against `PersistingStoreInterface` instead.
 * added support for shared locks
 * added `NoLock`
 * deprecated `NotSupportedException`, it shouldn't be thrown anymore.
 * deprecated `RetryTillSaveStore`, logic has been moved in `Lock` and is not needed anymore.
 * added `InMemoryStore`
 * added `PostgreSqlStore`
 * added the `LockFactory::CreateLockFromKey()` method.

5.1.0
-----

 * added the MongoDbStore supporting MongoDB servers >=2.2

5.0.0
-----

 * `Factory` has been removed, use `LockFactory` instead.
 * `StoreInterface` has been removed, use `BlockingStoreInterface` and `PersistingStoreInterface` instead.
 * removed the `waitAndSave()` method from `CombinedStore`, `MemcachedStore`, `RedisStore`, and `ZookeeperStore`

4.4.0
-----

 * added InvalidTtlException
 * deprecated `StoreInterface` in favor of `BlockingStoreInterface` and `PersistingStoreInterface`
 * `Factory` is deprecated, use `LockFactory` instead
 * `StoreFactory::createStore` allows PDO and Zookeeper DSN.
 * deprecated services `lock.store.flock`, `lock.store.semaphore`, `lock.store.memcached.abstract` and `lock.store.redis.abstract`,
   use `StoreFactory::createStore` instead.

4.2.0
-----

 * added the PDO Store
 * added a new Zookeeper Data Store for Lock Component

3.4.0
-----

 * added the component
