<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    return $routes->import('php_dsl_ba?.php');
};
