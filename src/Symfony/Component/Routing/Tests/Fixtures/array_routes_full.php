<?php

use Symfony\Component\Routing\Loader\Configurator\Routes;

return Routes::config([
    'a' => [
        'path' => '/a',
        'host' => 'example.com',
        'controller' => 'AppBundle:Blog:show',
        'locale' => 'en',
        'condition' => "request.headers.get('User-Agent') matches '/firefox/i'",
        'requirements' => ['slug' => '[a-z]+'],
    ],
    'b' => [
        'path' => ['en' => '/b-en', 'fr' => '/b-fr'],
    ],
    'c_alias' => [
        'alias' => 'a',
    ],
]);
