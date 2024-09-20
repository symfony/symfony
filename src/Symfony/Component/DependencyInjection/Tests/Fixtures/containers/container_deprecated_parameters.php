<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container->setParameter('foo_class', 'FooClass\\Foo');
$container->deprecateParameter('foo_class', 'symfony/test', '6.3');
$container->register('foo', '%foo_class%')
    ->setPublic(true)
;

return $container;
