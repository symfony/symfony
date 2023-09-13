<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('feature_toggle.routing.provider', \Closure::class)
        ->factory([\Closure::class, 'fromCallable'])
        ->args([
            [service('feature_toggle.feature_checker'), 'isEnabled'],
        ])
        ->tag('routing.expression_language_function', ['function' => 'isFeatureEnabled'])
    ;

    $services->get('feature_toggle.feature_checker')
        ->tag('routing.condition_service', ['alias' => 'feature'])
    ;
};
