<?php

use Symfony\Component\Routing\Loader\Configurator\Routes;

return Routes::config([
    'when@some-env' => [
        'x' => ['path' => '/x'],
    ],
    'a' => ['path' => '/a'],
]);
