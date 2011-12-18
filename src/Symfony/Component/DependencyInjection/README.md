DependencyInjection Component
=============================

DependencyInjection manages your services via a robust and flexible Dependency
Injection Container.

Here is a simple example that shows how to register services and parameters:

    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Reference;

    $sc = new ContainerBuilder();
    $sc
        ->register('foo', '%foo.class%')
        ->addArgument(new Reference('bar'))
    ;
    $sc->setParameter('foo.class', 'Foo');

    $sc->get('foo');

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/DependencyInjection
