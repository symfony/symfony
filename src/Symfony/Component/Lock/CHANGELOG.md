CHANGELOG
=========

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
