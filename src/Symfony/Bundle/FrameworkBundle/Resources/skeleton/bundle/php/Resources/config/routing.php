<?php

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$collection = new RouteCollection();
/*
$collection->add('homepage', new Route('/', array(
    '_controller' => '{{ bundle }}:Default:index',
)));
*/
return $collection;
