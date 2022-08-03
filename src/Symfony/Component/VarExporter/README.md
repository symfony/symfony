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
- `LazyGhostObjectTrait` can make a class behave as a lazy loading ghost object.

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

LazyGhostObjectTrait
--------------------

By using `LazyGhostObjectTrait` either directly in your classes or using
inheritance, you can make their instances able to lazy load themselves. This
works by creating these instances empty and by computing their state only when
accessing a property.

```php
FooMadeLazy extends Foo
{
    use LazyGhostObjectTrait;
}

// This closure will be called when the object needs to be initialized, ie when a property is accessed
$initializer = function (Foo $instance) {
    // [...] Use whatever heavy logic you need here to compute the $dependencies of the $instance
    $instance->__construct(...$dependencies);
};

$foo = FooMadeLazy::createLazyGhostObject($initializer);
```

You can also partially initialize the objects on a property-by-property basis by
adding two arguments to the initializer:

```php
$initializer = function (Foo $instance, string $propertyName, ?string $propertyScope) {
    if (Foo::class === $propertyScope && 'bar' === $propertyName) {
        return 123;
    }
    // [...] Add more logic for the other properties
};
```

Because lazy-initialization is not triggered when (un)setting a property, it's
also possible to do partial initialization by calling setters on a just-created
ghost object.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/var_exporter.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
