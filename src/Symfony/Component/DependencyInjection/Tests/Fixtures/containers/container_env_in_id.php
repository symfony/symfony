<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();

$container->setParameter('env(BAR)', 'bar');

$container->register('foo', 'stdClass')->setPublic(true)
   ->addArgument(new Reference('bar_%env(BAR)%'))
   ->addArgument(['baz_%env(BAR)%' => new Reference('baz_%env(BAR)%')]);

$container->register('bar', 'stdClass')->setPublic(true)
   ->addArgument(new Reference('bar_%env(BAR)%'));

$container->register('bar_%env(BAR)%', 'stdClass');
$container->register('baz_%env(BAR)%', 'stdClass');

return $container;
