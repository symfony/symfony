<?php

use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBag;

$container = new ContainerBuilder(new ParameterBag(array(
    'FOO'    => 'bar',
    'bar'    => 'foo is %foo bar',
    'values' => array(true, false, null, 0, 1000.3, 'true', 'false', 'null'),
)));

return $container;
