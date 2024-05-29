<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes): void {
    $routes->import('psr4-controllers-redirection/psr4-attributes.php');
};
