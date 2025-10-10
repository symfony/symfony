<?php

use Symfony\Config\RoutesConfig;

return new RoutesConfig([
    'a' => [
        'path' => '/a',
    ],
    'b' => [
        'path' => '/b',
        'methods' => ['GET'],
    ],
]);
