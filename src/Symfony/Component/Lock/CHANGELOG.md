CHANGELOG
=========

5.0.0
-----

* `Factory` has been removed, use `LockFactory` instead.
* `StoreInterface` has been removed, use `BlockingStoreInterface` and `PersistingStoreInterface` instead.

4.4.0
-----

 * added InvalidTtlException  
 * deprecated `StoreInterface` in favor of `BlockingStoreInterface` and `PersistingStoreInterface`
 * `Factory` is deprecated, use `LockFactory` instead

4.2.0
-----

 * added the PDO Store
 * added a new Zookeeper Data Store for Lock Component

3.4.0
-----

 * added the component
