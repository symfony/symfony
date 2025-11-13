VarExporter Component
=====================

The VarExporter component provides various tools to deal with the internal state
of objects:

- `VarExporter::export()` allows exporting any serializable PHP data structure to
  plain PHP code. While doing so, it preserves all the semantics associated with
  the serialization mechanism of PHP (`__wakeup`, `__sleep`, `Serializable`,
  `__serialize`, `__unserialize`);
- `Instantiator::instantiate()` creates an object and sets its properties without
  calling its constructor nor any other methods;
- `Hydrator::hydrate()` can set the properties of an existing object;
- `Lazy*Trait` can make a class behave as a lazy-loading ghost or virtual proxy.

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

Instantiator and Hydrator
-------------------------

`Instantiator::instantiate($class)` creates an object of the given class without
calling its constructor nor any other methods.

`Hydrator::hydrate()` sets the properties of an existing object, including
private and protected ones. For example:

```php
// Sets the public or protected $object->propertyName property
Hydrator::hydrate($object, ['propertyName' => $propertyValue]);

// Sets a private property defined on its parent Bar class:
Hydrator::hydrate($object, ["\0Bar\0privateBarProperty" => $propertyValue]);

// Alternative way to set the private $object->privateBarProperty property
Hydrator::hydrate($object, [], [
    Bar::class => ['privateBarProperty' => $propertyValue],
]);
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

In addition, this component provides traits and methods to aid in implementing
the ghost and proxy strategies in previous versions of PHP. Those are deprecated
when using PHP 8.4.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/var_exporter.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
