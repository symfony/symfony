<?php

use Symfony\Component\Routing\RouteCollection;

$collection = new RouteCollection();
$collection->addCollection($loader->import(__DIR__.'/routing.php'));

$collection->addCollection($loader->import("WebProfilerBundle/Resources/config/routing/profiler.xml"), '/_profiler');

return $collection;
