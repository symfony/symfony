<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return fn (RoutingConfigurator $routes) => $routes->import('php_dsl_ba?.php');
