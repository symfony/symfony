CHANGELOG
=========

3.2.0
-----

 * added TagAwareAdapter for tags-based invalidation
 * added PdoAdapter with PDO and Doctrine DBAL support
 * added PhpArrayAdapter and PhpFilesAdapter for OPcache-backed shared memory storage (PHP 7+ only)
 * added NullAdapter

3.1.0
-----

 * added the component with strict PSR-6 implementations
 * added ApcuAdapter, ArrayAdapter, FilesystemAdapter and RedisAdapter
 * added AbstractAdapter, ChainAdapter and ProxyAdapter
 * added DoctrineAdapter and DoctrineProvider for bidirectional interoperability with Doctrine Cache
