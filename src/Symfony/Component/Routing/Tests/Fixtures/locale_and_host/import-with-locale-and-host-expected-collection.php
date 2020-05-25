<?php

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return function (string $format) {
    $expectedRoutes = new RouteCollection();
    $expectedRoutes->add('imported.en', $route = new Route('/en/example'));
    $route->setHost('www.example.com');
    $route->setRequirement('_locale', 'en');
    $route->setDefault('_locale', 'en');
    $route->setDefault('_canonical_route', 'imported');
    $route->setDefault('_controller', 'ImportedController::someAction');
    $expectedRoutes->add('imported.nl', $route = new Route('/nl/voorbeeld'));
    $route->setHost('www.example.nl');
    $route->setRequirement('_locale', 'nl');
    $route->setDefault('_locale', 'nl');
    $route->setDefault('_canonical_route', 'imported');
    $route->setDefault('_controller', 'ImportedController::someAction');
    $expectedRoutes->add('imported_not_localized.en', $route = new Route('/en/here'));
    $route->setHost('www.example.com');
    $route->setRequirement('_locale', 'en');
    $route->setDefault('_locale', 'en');
    $route->setDefault('_canonical_route', 'imported_not_localized');
    $route->setDefault('_controller', 'ImportedController::someAction');
    $expectedRoutes->add('imported_not_localized.nl', $route = new Route('/nl/here'));
    $route->setHost('www.example.nl');
    $route->setRequirement('_locale', 'nl');
    $route->setDefault('_locale', 'nl');
    $route->setDefault('_canonical_route', 'imported_not_localized');
    $route->setDefault('_controller', 'ImportedController::someAction');
    $expectedRoutes->add('imported_single_host.en', $route = new Route('/en/here_again'));
    $route->setHost('www.example.com');
    $route->setRequirement('_locale', 'en');
    $route->setDefault('_locale', 'en');
    $route->setDefault('_canonical_route', 'imported_single_host');
    $route->setDefault('_controller', 'ImportedController::someAction');
    $expectedRoutes->add('imported_single_host.nl', $route = new Route('/nl/here_again'));
    $route->setHost('www.example.nl');
    $route->setRequirement('_locale', 'nl');
    $route->setDefault('_locale', 'nl');
    $route->setDefault('_canonical_route', 'imported_single_host');
    $route->setDefault('_controller', 'ImportedController::someAction');

    $expectedRoutes->addResource(new FileResource(__DIR__."/imported.$format"));
    $expectedRoutes->addResource(new FileResource(__DIR__."/importer-with-locale-and-host.$format"));

    return $expectedRoutes;
};
