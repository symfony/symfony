<?php

if ('prod' !== $env) {
    return;
}

return [
    'imports' => [
        'nested_config_builder.php',
    ],
    'acme' => [
        'color' => 'blue',
    ],
];
