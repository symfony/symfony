ClassLoader Component
=====================

ClassLoader loads your project classes automatically if they follow some
standard PHP conventions.

The ClassLoader object is able to autoload classes that implement the PSR-0
standard or the PEAR naming convention.

First, register the autoloader:

```php
require_once __DIR__.'/src/Symfony/Component/ClassLoader/ClassLoader.php';

use Symfony\Component\ClassLoader\ClassLoader;

$loader = new ClassLoader();
$loader->register();
```

Then, register some namespaces with the `addPrefix()` method:

```php
$loader->addPrefix('Symfony', __DIR__.'/src');
$loader->addPrefix('Monolog', __DIR__.'/vendor/monolog/src');
```

The `addPrefix()` method takes a namespace prefix and a path where to look for
the classes as arguments.

You can also register a sub-namespaces:

```php
$loader->addPrefix('Doctrine\\Common', __DIR__.'/vendor/doctrine-common/lib');
```

The order of registration is significant and the first registered namespace
takes precedence over later registered one.

You can also register more than one path for a given namespace:

```php
$loader->addPrefix('Symfony', array(__DIR__.'/src', __DIR__.'/symfony/src'));
```

Alternatively, you can use the `addPrefixes()` method to register more
than one namespace at once:

```php
$loader->addPrefixes(array(
    'Symfony' => array(__DIR__.'/src', __DIR__.'/symfony/src'),
    'Doctrine\\Common' => __DIR__.'/vendor/doctrine-common/lib',
    'Doctrine' => __DIR__.'/vendor/doctrine/lib',
    'Monolog' => __DIR__.'/vendor/monolog/src',
));
```

For better performance, you can use the APC class loader:

```php
require_once __DIR__.'/src/Symfony/Component/ClassLoader/ClassLoader.php';
require_once __DIR__.'/src/Symfony/Component/ClassLoader/ApcClassLoader.php';

use Symfony\Component\ClassLoader\ClassLoader;
use Symfony\Component\ClassLoader\ApcClassLoader;

$loader = new ClassLoader();
$loader->addPrefix('Symfony', __DIR__.'/src');

$loader = new ApcClassLoader('apc.prefix.', $loader);
$loader->register();
```

Furthermore, the component provides tools to aggregate classes into a single
file, which is especially useful to improve performance on servers that do not
provide byte caches.

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/ClassLoader/
    $ composer install
    $ phpunit
