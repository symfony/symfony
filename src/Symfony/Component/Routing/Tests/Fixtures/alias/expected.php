<?php

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (string $format) {
    $expectedRoutes = new RouteCollection();

    $expectedRoutes->add('route', new Route('/hello'));
    $expectedRoutes->setAlias('alias', 'route');
    $expectedRoutes->setAlias('alias2', 'route');
    $expectedRoutes->setAlias('deep', 'alias');
    $expectedRoutes->setAlias('overrided', 'route');

    $expectedRoutes->addResource(new FileResource(__DIR__."/alias.$format"));
    if ($format === 'yaml') {
        $expectedRoutes->addResource(new FileResource(__DIR__."/override.$format"));
    }

    return $expectedRoutes;
};
