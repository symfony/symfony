<?php

require_once __DIR__.'/../includes/classes.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Reference;

$container = new Builder();
$container->
    register('foo', 'FooClass')->
    addArgument(new Reference('bar'))
;

return $container;
