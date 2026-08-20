MongoDB Cache
=============

Provides MongoDB integration for the Symfony Cache component.

DSN example
-----------

```
CACHE_DSN=mongodb://user:pass@mongodb1.example.com:27017/db_name?collection_name=cache&namespace=thecacheprefix
```

The adapter reads the database from the DSN path (or the `database_name`
option) and the collection from the `collection_name` query parameter (or the
`collection_name` option). The key prefix is the `$namespace` constructor
argument, or, when that one is empty, the `namespace` query parameter. Any
other query parameter is passed to the MongoDB driver, so
[connection options](https://www.mongodb.com/docs/manual/reference/connection-string-options/)
keep working:

```
CACHE_DSN=mongodb+srv://mongodb.example.com/db_name?collection_name=cache&readPreference=secondaryPreferred
```

The read and write concerns and the read preference are inherited from the
injected `MongoDB\Collection`, or from the connection string and the
`uriOptions` option when a DSN is given (for example `readPreference`,
`maxStalenessSeconds`, `readPreferenceTags` or `w`). The adapter never
overrides them. Reading from a secondary is a sensible choice for a cache.

Create a TTL index on the `expires_at` field to let the server remove expired
entries automatically. The adapter exposes a `setup()` method that creates it
(and, for the tag aware adapter, an index on `tags`).

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
