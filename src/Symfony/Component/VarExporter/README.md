VarExporter Component
=====================

The VarExporter component provides various tools to deal with the internal state
of objects:

- `VarExporter::export()` allows exporting any serializable PHP data structure to
  plain PHP code. While doing so, it preserves all the semantics associated with
  the serialization mechanism of PHP (`__wakeup`, `__sleep`, `Serializable`,
  `__serialize`, `__unserialize`);
- `DeepCloner` deep-clones PHP values while preserving copy-on-write benefits
  for strings and arrays, making it faster and more memory efficient than
  `unserialize(serialize())`;
- `ProxyHelper::generateLazyProxy()` generates lazy-loading decorators for
  abstract or internal classes and for interfaces (use native lazy objects
  for regular concrete classes).

The component depends on the native [`ext-deepclone`](https://github.com/symfony/php-ext-deepclone)
extension for maximum performance, or on [its polyfill](https://github.com/symfony/polyfill/tree/main/src/DeepClone)
as a fallback. In addition to functions `deepclone_to_array()` and `deepclone_from_array()`
which are leveraged by `DeepCloner` and `VarExporter::export()`, the extension
provides a `deepclone_hydrate()` function that lets you instantiate / hydrate objects
without calling their constructor, including private, protected and readonly properties.

VarExporter::export()
---------------------

The reason to use `VarExporter::export()` *vs* `serialize()` or
[igbinary](https://github.com/igbinary/igbinary) is performance: thanks to
OPcache, the resulting code is significantly faster and more memory efficient
than using `unserialize()` or `igbinary_unserialize()`.

Unlike `var_export()`, this works on any serializable PHP value.

It also provides a few improvements over `var_export()`/`serialize()`:

 * the output is PSR-2 compatible;
 * the output can be re-indented without messing up with `\r` or `\n` in the data;
 * missing classes throw a `ClassNotFoundException` instead of being unserialized
   to `PHP_Incomplete_Class` objects;
 * references involving `SplObjectStorage`, `ArrayObject` or `ArrayIterator`
   instances are preserved;
 * `Reflection*`, `IteratorIterator` and `RecursiveIteratorIterator` classes
   throw an exception when being serialized (their unserialized version is broken
   anyway, see https://bugs.php.net/76737).

DeepCloner
----------

`DeepCloner::deepClone()` deep-clones a PHP value. Unlike
`unserialize(serialize())`, it preserves PHP's copy-on-write semantics for
strings and arrays, resulting in lower memory usage and better performance:

```php
$clone = DeepCloner::deepClone($originalObject);
```

For repeated cloning of the same structure, create an instance to amortize the
cost of graph analysis:

```php
$cloner = new DeepCloner($prototype);
$clone1 = $cloner->clone();
$clone2 = $cloner->clone();
```

Lazy Proxies
------------

Since version 8.4, PHP provides support for lazy objects via the reflection API.
This native API works with concrete classes. It doesn't with abstracts nor with
internal ones.

This components provides helpers to generate lazy objects using the decorator
pattern, which works with abstract or internal classes and with interfaces:

```php
$proxyCode = ProxyHelper::generateLazyProxy(new ReflectionClass(AbstractFoo::class));
// $proxyCode should be dumped into a file in production envs
eval('class FooLazyProxy'.$proxyCode);

$foo = FooLazyProxy::createLazyProxy(initializer: function (): AbstractFoo {
    // [...] Use whatever heavy logic you need here
    // to compute the $dependencies of the $instance
    $instance = new Foo(...$dependencies);
    // [...] Call setters, etc. if needed

    return $instance;
});
// $foo is now a lazy-loading decorator object. The initializer will
// be called only when and if a *method* is called.
```

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/var_exporter.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
