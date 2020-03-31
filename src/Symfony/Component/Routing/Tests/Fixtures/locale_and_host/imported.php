<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes
        ->add('imported', ['nl' => '/voorbeeld', 'en' => '/example'])
            ->controller('ImportedController::someAction')
            ->host([
                'nl' => 'www.custom.nl',
                'en' => 'www.custom.com',
            ])
        ->add('imported_not_localized', '/here')
            ->controller('ImportedController::someAction')
        ->add('imported_single_host', '/here_again')
            ->controller('ImportedController::someAction')
            ->host('www.custom.com')
    ;
};
