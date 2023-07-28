<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('toggle_feature.routing.provider', \Closure::class)
        ->factory([\Closure::class, 'fromCallable'])
        ->args([
            [service('toggle_feature.feature_checker'), 'isEnabled'],
        ])
        ->tag('routing.expression_language_function', ['function' => 'isFeatureEnabled'])
    ;

    $services->get('toggle_feature.feature_checker')
        ->tag('routing.condition_service', ['alias' => 'feature'])
    ;
};
