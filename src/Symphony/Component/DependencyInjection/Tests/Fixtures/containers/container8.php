<?php

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\ParameterBag\ParameterBag;

$container = new ContainerBuilder(new ParameterBag(array(
    'foo' => '%baz%',
    'baz' => 'bar',
    'bar' => 'foo is %%foo bar',
    'escape' => '@escapeme',
    'values' => array(true, false, null, 0, 1000.3, 'true', 'false', 'null'),
    'binary' => "\xf0\xf0\xf0\xf0",
    'binary-control-char' => "This is a Bell char \x07",
)));

return $container;
