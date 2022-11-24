VarExporter Component
=====================

The VarExporter component provides various tools to deal with the internal state
of objects:

- `VarExporter::export()` allows exporting any serializable PHP data structure to
  plain PHP code. While doing so, it preserves all the semantics associated with
  the serialization mechanism of PHP (`__wakeup`, `__sleep`, `Serializable`,
  `__serialize`, `__unserialize`.)
- `Instantiator::instantiate()` creates an object and sets its properties without
  calling its constructor nor any other methods.
- `Hydrator::hydrate()` can set the properties of an existing object.
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
 * the output can be re-indented without messing up with `\r` or `\n` in the data
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

`Lazy*Trait`
------------

The component provides two lazy-loading patterns: ghost objects and virtual
proxies (see https://martinfowler.com/eaaCatalog/lazyLoad.html for reference.)

Ghost objects work only with concrete and non-internal classes. In the generic
case, they are not compatible with using factories in their initializer.

Virtual proxies work with concrete, abstract or internal classes. They provide an
API that looks like the actual objects and forward calls to them. They can cause
identity problems because proxies might not be seen as equivalents to the actual
objects they proxy.

Because of this identity problem, ghost objects should be preferred when
possible. Exceptions thrown by the `ProxyHelper` class can help decide when it
can be used or not.

Ghost objects and virtual proxies both provide implementations for the
`LazyObjectInterface` which allows resetting them to their initial state or to
forcibly initialize them when needed. Note that resetting a ghost object skips
its read-only properties. You should use a virtual proxy to reset read-only
properties.

### `LazyGhostTrait`

By using `LazyGhostTrait` either directly in your classes or by using
`ProxyHelper::generateLazyGhost()`, you can make their instances lazy-loadable.
This works by creating these instances empty and by computing their state only
when accessing a property.

```php
class FooLazyGhost extends Foo
{
    use LazyGhostTrait;
}

$foo = FooLazyGhost::createLazyGhost(initializer: function (Foo $instance): void {
    // [...] Use whatever heavy logic you need here
    // to compute the $dependencies of the $instance
    $instance->__construct(...$dependencies);
    // [...] Call setters, etc. if needed
});

// $foo is now a lazy-loading ghost object. The initializer will
// be called only when and if a *property* is accessed.
```

You can also partially initialize the objects on a property-by-property basis by
adding two arguments to the initializer:

```php
$initializer = function (Foo $instance, string $propertyName, ?string $propertyScope): mixed {
    if (Foo::class === $propertyScope && 'bar' === $propertyName) {
        return 123;
    }
    // [...] Add more logic for the other properties
};
```

### `LazyProxyTrait`

Alternatively, `LazyProxyTrait` can be used to create virtual proxies:

```php
$proxyCode = ProxyHelper::generateLazyProxy(new ReflectionClass(Foo::class));
// $proxyCode contains the reference to LazyProxyTrait
// and should be dumped into a file in production envs
eval('class FooLazyProxy'.$proxyCode);

$foo = FooLazyProxy::createLazyProxy(initializer: function (): Foo {
    // [...] Use whatever heavy logic you need here
    // to compute the $dependencies of the $instance
    $instance = new Foo(...$dependencies);
    // [...] Call setters, etc. if needed

    return $instance;
});
// $foo is now a lazy-loading virtual proxy object. The initializer will
// be called only when and if a *method* is called.
```

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/var_exporter.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
