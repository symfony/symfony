<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $sub = $routes->collection('c_')->prefix(['en' => '/categories', 'fr' => '/categorias'], false);

    $sub->add('slash', '/');
    $sub->add('show', '/{id}');
};
