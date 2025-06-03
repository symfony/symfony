<?php

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return function (string $format) {
    $expectedRoutes = new RouteCollection();
    $expectedRoutes->add('static.en', $route = new Route('/example'));
    $route->setHost('www.example.com');
    $route->setRequirement('_locale', 'en');
    $route->setDefault('_locale', 'en');
    $route->setDefault('_canonical_route', 'static');
    $expectedRoutes->add('static.nl', $route = new Route('/example'));
    $route->setHost('www.example.nl');
    $route->setRequirement('_locale', 'nl');
    $route->setDefault('_locale', 'nl');
    $route->setDefault('_canonical_route', 'static');

    $expectedRoutes->addResource(new FileResource(__DIR__."/route-with-hosts.$format"));

    return $expectedRoutes;
};
