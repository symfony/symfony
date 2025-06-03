<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AcmeConfig;

if ('prod' !== $env) {
    return;
}

return function (AcmeConfig $config, ContainerConfigurator $c) {
    $c->import('nested_config_builder.php');

    $config->color('blue');
};
