<?php

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$collection = new RouteCollection();
$collection->addRoute('homepage', new Route('/', array(
    '_controller' => 'FrameworkBundle:Default:index',
)));
$collection->addCollection($loader->import("HelloBundle/Resources/config/routing.php"));

return $collection;
