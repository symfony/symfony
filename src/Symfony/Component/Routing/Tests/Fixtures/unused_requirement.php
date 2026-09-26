<?php

use Symfony\Component\Routing\Loader\Configurator\Routes;

return Routes::config([
    'foo_url' => [
        'path' => '/foo-url/{clientId}',
        'requirements' => ['client' => '\d+'],
    ],
]);
