<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $c) {
    $c->services()
        ->set('bar_service', stdClass::class)
    ;
};
