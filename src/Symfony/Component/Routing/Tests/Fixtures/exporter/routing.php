<?php

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$collection = new RouteCollection();
$collection->add('foo', new Route(
    '/foo/{bar}', // path
    array (  'def' => 'test',), // defaults
    array (  'bar' => 'baz|symfony',), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('foobar', new Route(
    '/foo/{bar}', // path
    array (  'bar' => 'toto',), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('bar', new Route(
    '/bar/{foo}', // path
    array (), // defaults
    array (  '_method' => 'GET|head',), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (  0 => 'GET',  1 => 'HEAD',), // methods
    '' // condition
));
$collection->add('baragain', new Route(
    '/baragain/{foo}', // path
    array (), // defaults
    array (  '_method' => 'get|post',), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (  0 => 'GET',  1 => 'POST',), // methods
    '' // condition
));
$collection->add('baz', new Route(
    '/test/baz', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('baz2', new Route(
    '/test/baz.html', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('baz3', new Route(
    '/test/baz3/', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('baz4', new Route(
    '/test/{foo}/', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('baz5', new Route(
    '/test/{foo}/', // path
    array (), // defaults
    array (  '_method' => 'get',), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (  0 => 'GET',), // methods
    '' // condition
));
$collection->add('baz5unsafe', new Route(
    '/testunsafe/{foo}/', // path
    array (), // defaults
    array (  '_method' => 'post',), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (  0 => 'POST',), // methods
    '' // condition
));
$collection->add('baz6', new Route(
    '/test/baz', // path
    array (  'foo' => 'bar baz',), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('baz7', new Route(
    '/te st/baz', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('baz8', new Route(
    '/te\\ st/baz', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('baz9', new Route(
    '/test/{baz}', // path
    array (), // defaults
    array (  'baz' => 'te\\\\ st',), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route1', new Route(
    '/route1', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    'a.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route2', new Route(
    '/c2/route2', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    'a.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route3', new Route(
    '/c2/route3', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    'b.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route4', new Route(
    '/route4', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    'a.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route5', new Route(
    '/route5', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    'c.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route6', new Route(
    '/route6', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route11', new Route(
    '/route11', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '{var1}.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route12', new Route(
    '/route12', // path
    array (  'var1' => 'val',), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '{var1}.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route13', new Route(
    '/route13/{name}', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '{var1}.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route14', new Route(
    '/route14/{name}', // path
    array (  'var1' => 'val',), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '{var1}.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route15', new Route(
    '/route15/{name}', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    'c.example.com', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route16', new Route(
    '/route16/{name}', // path
    array (  'var1' => 'val',), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
$collection->add('route17', new Route(
    '/route17', // path
    array (), // defaults
    array (), // requirements
    array (  'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',), // options
    '', // host
    array (), // schemes
    array (), // methods
    '' // condition
));
return $collection;