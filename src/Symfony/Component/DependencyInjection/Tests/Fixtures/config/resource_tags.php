<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services->set('foo', stdClass::class)
        ->resourceTag('my.tag', ['foo' => 'bar'])
        ->resourceTag('another.tag')
    ;
};
