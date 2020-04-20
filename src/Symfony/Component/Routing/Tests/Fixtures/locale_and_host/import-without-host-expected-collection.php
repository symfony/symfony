<?php

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return function (string $format) {
    $expectedRoutes = new RouteCollection();
    $expectedRoutes->add('imported.en', $route = new Route('/example'));
    $route->setHost('www.custom.com');
    $route->setRequirement('_locale', 'en');
    $route->setDefault('_locale', 'en');
    $route->setDefault('_canonical_route', 'imported');
    $route->setDefault('_controller', 'ImportedController::someAction');
    $expectedRoutes->add('imported.nl', $route = new Route('/voorbeeld'));
    $route->setHost('www.custom.nl');
    $route->setRequirement('_locale', 'nl');
    $route->setDefault('_locale', 'nl');
    $route->setDefault('_canonical_route', 'imported');
    $route->setDefault('_controller', 'ImportedController::someAction');
    $expectedRoutes->add('imported_not_localized', $route = new Route('/here'));
    $route->setDefault('_controller', 'ImportedController::someAction');
    $expectedRoutes->add('imported_single_host', $route = new Route('/here_again'));
    $route->setHost('www.custom.com');
    $route->setDefault('_controller', 'ImportedController::someAction');

    $expectedRoutes->addResource(new FileResource(__DIR__."/imported.$format"));
    $expectedRoutes->addResource(new FileResource(__DIR__."/importer-without-host.$format"));

    return $expectedRoutes;
};
