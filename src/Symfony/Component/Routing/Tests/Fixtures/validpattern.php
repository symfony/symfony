<?php

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$collection = new RouteCollection();
$collection->add('blog_show', new Route(
    '/blog/{slug}',
    ['_controller' => 'MyBlogBundle:Blog:show'],
    ['locale' => '\w+'],
    ['compiler_class' => 'RouteCompiler'],
    '{locale}.example.com',
    ['https'],
    ['GET', 'POST', 'put', 'OpTiOnS'],
    'context.getMethod() == "GET"'
));

return $collection;
