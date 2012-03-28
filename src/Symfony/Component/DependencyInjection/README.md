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

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Component/DependencyInjection/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export SYMFONY_CONFIG=../path/to/Config
    export SYMFONY_YAML=../path/to/Yaml
