VarExporter Component
=====================

The VarExporter component allows exporting any serializable PHP data structure to
plain PHP code. While doing so, it preserves all the semantics associated with
the serialization mechanism of PHP (`__wakeup`, `__sleep`, `Serializable`,
`__serialize`, `__unserialize`).

It also provides an instantiator that allows creating and populating objects
without calling their constructor nor any other methods.

The reason to use this component *vs* `serialize()` or
[igbinary](https://github.com/igbinary/igbinary) is performance: thanks to
OPcache, the resulting code is significantly faster and more memory efficient
than using `unserialize()` or `igbinary_unserialize()`.

Unlike `var_export()`, this works on any serializable PHP value.

It also provides a few improvements over `var_export()`/`serialize()`:

 * the output is PSR-2 compatible;
 * the output can be re-indented without messing up with `\r` or `\n` in the data
 * missing classes throw a `ClassNotFoundException` instead of being unserialized to
   `PHP_Incomplete_Class` objects;
 * references involving `SplObjectStorage`, `ArrayObject` or `ArrayIterator`
   instances are preserved;
 * `Reflection*`, `IteratorIterator` and `RecursiveIteratorIterator` classes
   throw an exception when being serialized (their unserialized version is broken
   anyway, see https://bugs.php.net/76737).

Resources
---------

  * [Documentation](https://symfony.com/doc/current/components/var_exporter/introduction.html)
  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
