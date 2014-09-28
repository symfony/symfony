DependencyInjection Component
=============================

DependencyInjection manages your services via a robust and flexible Dependency
Injection Container.

Here is a simple example that shows how to register services and parameters:

```php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$sc = new ContainerBuilder();
$sc
    ->register('foo', '%foo.class%')
    ->addArgument(new Reference('bar'))
;
$sc->setParameter('foo.class', 'Foo');

$sc->get('foo');
```

Method Calls (Setter Injection):

```php
$sc = new ContainerBuilder();

$sc
    ->register('bar', '%bar.class%')
    ->addMethodCall('setFoo', array(new Reference('foo')))
;
$sc->setParameter('bar.class', 'Bar');

$sc->get('bar');
```

Factory Class:

If your service is retrieved by calling a static method:

```php
$sc = new ContainerBuilder();

$sc
    ->register('bar', '%bar.class%')
    ->setFactoryClass('%bar.class%')
    ->setFactoryMethod('getInstance')
    ->addArgument('Aarrg!!!')
;
$sc->setParameter('bar.class', 'Bar');

$sc->get('bar');
```

File Include:

For some services, especially those that are difficult or impossible to
autoload, you may need the container to include a file before
instantiating your class.

```php
$sc = new ContainerBuilder();

$sc
    ->register('bar', '%bar.class%')
    ->setFile('/path/to/file')
    ->addArgument('Aarrg!!!')
;
$sc->setParameter('bar.class', 'Bar');

$sc->get('bar');
```

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/DependencyInjection/
    $ composer.phar install
    $ phpunit
