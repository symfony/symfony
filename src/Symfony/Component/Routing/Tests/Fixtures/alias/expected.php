<?php

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (string $format) {
    $expectedRoutes = new RouteCollection();

    $expectedRoutes->add('route', new Route('/hello'));
    $expectedRoutes->addAlias('alias', 'route');
    $expectedRoutes->addAlias('deprecated', 'route')
        ->setDeprecated('foo/bar', '1.0.0', '');
    $expectedRoutes->addAlias('deprecated-with-custom-message', 'route')
        ->setDeprecated('foo/bar', '1.0.0', 'foo %alias_id%.');
    $expectedRoutes->addAlias('deep', 'alias');
    $expectedRoutes->addAlias('overrided', 'route');

    $expectedRoutes->addResource(new FileResource(__DIR__."/alias.$format"));
    if ('yaml' === $format) {
        $expectedRoutes->addResource(new FileResource(__DIR__."/override.$format"));
    }

    return $expectedRoutes;
};
