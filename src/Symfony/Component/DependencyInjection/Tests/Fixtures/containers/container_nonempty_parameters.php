<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;

$container = new ContainerBuilder();
$container->parameterCannotBeEmpty('bar', 'Did you forget to configure the "foo.bar" option?');
$container->register('foo', 'stdClass')
    ->setArguments([new Parameter('bar')])
    ->setPublic(true)
;

return $container;
