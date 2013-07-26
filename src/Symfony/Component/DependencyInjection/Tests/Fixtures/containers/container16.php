<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Scope;

$container = new ContainerBuilder();
$container->
    addScope(new Scope("request"))
;

return $container;
