DependencyInjection Component
=============================

Even the DependencyInjection component is really easy. It has many features, but its core
is simple to use. See how easy it is to configure a service that relies on another service:

```
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

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/DependencyInjection
