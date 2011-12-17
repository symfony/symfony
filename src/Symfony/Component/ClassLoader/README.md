ClassLoader Component
=====================

The ClassLoader component provides an autoloader that implements the PSR-0 standard
(which is a standard way to autoload namespaced classes as available in PHP 5.3).
It is also able to load classes that use the PEAR naming convention. It is really
flexible as it can look for classes in different directories based on a sub-namespace.
You can even give more than one directory for one namespace:

```
require_once __DIR__.'/src/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
    'Symfony'          => array(__DIR__.'/src', __DIR__.'/symfony/src'),
    'Doctrine\\Common' => __DIR__.'/vendor/doctrine-common/lib',
    'Doctrine\\DBAL'   => __DIR__.'/vendor/doctrine-dbal/lib',
    'Doctrine'         => __DIR__.'/vendor/doctrine/lib',
    'Monolog'          => __DIR__.'/vendor/monolog/src',
));
$loader->registerPrefixes(array(
    'Twig_' => __DIR__.'/vendor/twig/lib',
));
$loader->register();
```

Most of the time, the Symfony2 ClassLoader is all you need to autoload all your project classes.
And for better performance, you can use an APC cached version of the universal class loader or
the map class loader.

Furthermore it provides tools to aggregate classes into a single file, which is especially
useful to improve performance on servers that do not provide byte caches.

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/ClassLoader
